<?php

namespace App\Http\Controllers;



use App\Support\PermisosSupervisor;use App\Services\ServicioAuditoria;
use App\Services\ServicioFinancieroTurno;
use App\Services\ServicioPrecioVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ControladorDescuentos extends ControladorBaseGrifo
{
    public function indice()
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD','OPERADOR','LECTURA']);

        $q = DB::table('descuentos as d')
            ->leftJoin('turnos_operativos as t', 't.id', '=', 'd.turno_operativo_id')
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->leftJoin('trabajadores as tr', 'tr.id', '=', 't.trabajador_id')
            ->leftJoin('productos as p', 'p.id', '=', 'd.producto_id')
            ->where('d.establecimiento_id', $this->establecimientoId())
            ->select(
                'd.*',
                'tt.codigo as turno_codigo',
                'tr.nombre_completo as trabajador',
                'p.codigo as producto_codigo',
                'p.nombre as producto'
            );

        if ($this->esOperador()) {
            $trabajadorId = $this->exigirTrabajadorVinculado();
            $q->where('t.trabajador_id', $trabajadorId);
        }

        $descuentos = $q->orderByDesc('d.fecha_descuento')
            ->orderByDesc('d.id')
            ->paginate(30);

        return view('descuentos.indice', compact('descuentos'));
    }

    public function crear(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR']);

        $turnos = $this->turnosDisponibles();
        $productos = $this->productosDisponibles();
        $turnoPreseleccionado = $request->integer('turno_operativo_id') ?: null;
        if ($turnoPreseleccionado) {
            PermisosSupervisor::exigirTurnoEditable($turnoPreseleccionado);
        }
        $descuento = null;
        $esAdministrador = auth()->user()->rol === 'ADMINISTRADOR';

        return view('descuentos.formulario', compact(
            'turnos','productos','turnoPreseleccionado','descuento','esAdministrador'
        ));
    }

    public function precio(Request $request, ServicioPrecioVenta $servicioPrecioVenta)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR']);

        $datos = $request->validate([
            'turno_operativo_id' => ['required','integer',Rule::exists('turnos_operativos','id')],
            'producto_id' => ['required','integer',Rule::exists('productos','id')],
        ]);

        $turno = $this->turnoAutorizado((int) $datos['turno_operativo_id']);
        $producto = DB::table('productos')->where('id', $datos['producto_id'])->first();
        abort_unless($producto, 404);

        $precio = $servicioPrecioVenta->obtenerParaTurno(
            $this->establecimientoId(),
            (int) $datos['producto_id'],
            (int) $turno->id
        );

        if (! $precio) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No existe un precio de venta vigente para el producto en ese turno.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'precio' => number_format((float) $precio->precio, 4, '.', ''),
            'producto' => $producto->codigo ?? $producto->nombre,
        ]);
    }

    public function guardar(
        Request $request,
        ServicioPrecioVenta $servicioPrecioVenta,
        ServicioFinancieroTurno $servicioFinancieroTurno
    ) {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR']);

        $datos = $this->validarFormulario($request);
        $turno = $this->turnoAutorizado((int) $datos['turno_operativo_id']);
        PermisosSupervisor::exigirTurnoEditable((int)$turno->id);
        $precio = $this->resolverPrecio($request, $servicioPrecioVenta, $turno, (int) $datos['producto_id']);
        $importe = round((float) $datos['galones'] * $precio, 2);

        $insertar = [
            'establecimiento_id' => $this->establecimientoId(),
            'turno_operativo_id' => (int) $turno->id,
            'fecha_descuento' => $turno->fecha_operacion,
            'producto_id' => (int) $datos['producto_id'],
            'galones' => round((float) $datos['galones'], 3),
            'precio_unitario_descuento' => $precio,
            'importe_efectivo' => $importe,
            'motivo' => $datos['motivo'] ?? null,
            'hoja_origen' => 'SISTEMA',
        ];

        DB::transaction(function () use ($request, $turno, $insertar, $servicioFinancieroTurno) {
            PermisosSupervisor::exigirTurnoEditable((int) $turno->id, true);
            $id = DB::table('descuentos')->insertGetId($insertar);
            $servicioFinancieroTurno->sincronizar((int) $turno->id);
            ServicioAuditoria::registrar('DESCUENTO', $id, 'CREAR', null, $insertar, $request);
        }, 3);

        if ($request->filled('volver_turno_id')) {
            $turnoRetorno = (int) $request->input('volver_turno_id');

            if ($turnoRetorno > 0) {
                return redirect()->route('turnos.mostrar', $turnoRetorno)
                    ->with('exito', 'Descuento registrado. El detalle financiero del turno fue recalculado.');
            }
        }

        return redirect()->route('descuentos.indice')
            ->with('exito', 'Descuento registrado. El detalle financiero del turno fue recalculado.');
    }

    public function editar(int $descuento)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR']);

        $registro = DB::table('descuentos')
            ->where('id', $descuento)
            ->where('establecimiento_id', $this->establecimientoId())
            ->first();
        abort_unless($registro, 404);
        if ($registro->turno_operativo_id !== null) {
            PermisosSupervisor::exigirTurnoEditable((int)$registro->turno_operativo_id);
        }

        $turnos = $this->turnosDisponibles();
        $productos = $this->productosDisponibles();
        $turnoPreseleccionado = (int) $registro->turno_operativo_id;
        $esAdministrador = auth()->user()->rol === 'ADMINISTRADOR';
        $descuento = $registro;

        return view('descuentos.formulario', compact(
            'turnos','productos','turnoPreseleccionado','descuento','esAdministrador'
        ));
    }

    public function actualizar(
        Request $request,
        int $descuento,
        ServicioPrecioVenta $servicioPrecioVenta,
        ServicioFinancieroTurno $servicioFinancieroTurno
    ) {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR']);

        $datos = $this->validarFormulario($request);

        DB::transaction(function () use ($request, $descuento, $datos, $servicioPrecioVenta, $servicioFinancieroTurno) {
            $anterior = DB::table('descuentos')
                ->where('id', $descuento)
                ->where('establecimiento_id', $this->establecimientoId())
                ->lockForUpdate()->first();
            abort_unless($anterior, 404);
            $turno = $this->turnoAutorizado((int) $datos['turno_operativo_id'], false);
            $turnosARecalcular = collect([(int) $anterior->turno_operativo_id, (int) $turno->id])
                ->filter()->unique()->sort();
            foreach ($turnosARecalcular as $turnoId) {
                PermisosSupervisor::exigirTurnoEditable($turnoId, true);
            }
            $precio = $this->resolverPrecio($request, $servicioPrecioVenta, $turno, (int) $datos['producto_id'], $anterior);
            $importe = round((float) $datos['galones'] * $precio, 2);

            $actualizar = [
                'turno_operativo_id' => (int) $turno->id,
                'fecha_descuento' => $turno->fecha_operacion,
                'producto_id' => (int) $datos['producto_id'],
                'galones' => round((float) $datos['galones'], 3),
                'precio_unitario_descuento' => $precio,
                'importe_efectivo' => $importe,
                'motivo' => $datos['motivo'] ?? null,
            ];

            DB::table('descuentos')->where('id', $descuento)->update($actualizar);

            foreach ($turnosARecalcular as $turnoId) {
                $servicioFinancieroTurno->sincronizar((int) $turnoId);
            }

            ServicioAuditoria::registrar('DESCUENTO', $descuento, 'ACTUALIZAR', (array) $anterior, $actualizar, $request);
        }, 3);

        return redirect()->route('descuentos.indice')
            ->with('exito', 'Descuento actualizado. El detalle financiero del turno fue recalculado.');
    }

    private function validarFormulario(Request $request): array
    {
        return $request->validate([
            'turno_operativo_id' => ['required','integer',Rule::exists('turnos_operativos','id')],
            'producto_id' => ['required','integer',Rule::exists('productos','id')],
            'galones' => ['required','numeric','min:0.001'],
            'precio_unitario_descuento' => ['nullable','numeric','min:0'],
            'motivo' => ['nullable','string','max:255'],
        ]);
    }

    private function resolverPrecio(
        Request $request,
        ServicioPrecioVenta $servicioPrecioVenta,
        object $turno,
        int $productoId,
        ?object $anterior = null
    ): float {
        $vigente = $servicioPrecioVenta->obtenerParaTurno(
            $this->establecimientoId(),
            $productoId,
            (int) $turno->id
        );

        if (! $vigente) {
            throw ValidationException::withMessages([
                'precio_unitario_descuento' => 'No existe un precio vigente para el producto en ese turno.',
            ]);
        }

        if (auth()->user()->rol === 'ADMINISTRADOR' && $request->filled('precio_unitario_descuento')) {
            return round((float) $request->precio_unitario_descuento, 4);
        }

        // Si otro rol edita el mismo descuento, conserva un posible precio especial
        // que previamente haya sido autorizado por ADMINISTRADOR.
        if ($anterior
            && (int) $anterior->turno_operativo_id === (int) $turno->id
            && (int) $anterior->producto_id === $productoId
            && $anterior->precio_unitario_descuento !== null
        ) {
            return round((float) $anterior->precio_unitario_descuento, 4);
        }

        return round((float) $vigente->precio, 4);
    }

    private function turnoAutorizado(int $turnoId, bool $restringirOperador = true): object
    {
        $q = DB::table('turnos_operativos')
            ->where('id', $turnoId)
            ->where('establecimiento_id', $this->establecimientoId());

        if ($restringirOperador && $this->esOperador()) {
            $q->where('trabajador_id', $this->exigirTrabajadorVinculado());
        }

        $turno = $q->first();
        abort_unless($turno, 404);

        return $turno;
    }

    private function turnosDisponibles()
    {
        $q = DB::table('turnos_operativos as t')
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->leftJoin('trabajadores as tr', 'tr.id', '=', 't.trabajador_id')
            ->where('t.establecimiento_id', $this->establecimientoId());

        if ($this->esOperador()) {
            $q->where('t.trabajador_id', $this->exigirTrabajadorVinculado());
        }
        PermisosSupervisor::filtrarTurnosEditables($q);

        return $q->orderByDesc('t.fecha_operacion')
            ->orderByDesc('t.id')
            ->limit(250)
            ->get([
                't.id','t.fecha_operacion','tt.codigo as turno_codigo','tr.nombre_completo as trabajador'
            ]);
    }

    private function productosDisponibles()
    {
        return DB::table('productos as p')
            ->join('mangueras as m', 'm.producto_id', '=', 'p.id')
            ->join('surtidores as s', 's.id', '=', 'm.surtidor_id')
            ->where('s.establecimiento_id', $this->establecimientoId())
            ->where(function ($q) {
                $q->whereNull('m.activo')->orWhere('m.activo', 1);
            })
            ->select('p.id','p.codigo','p.nombre')
            ->distinct()
            ->orderBy('p.id')
            ->get();
    }
}
