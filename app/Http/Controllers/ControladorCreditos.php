<?php

namespace App\Http\Controllers;



use App\Support\PermisosSupervisor;use App\Services\ServicioAuditoria;
use App\Services\ServicioPrecioVenta;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ControladorCreditos extends ControladorBaseGrifo
{
    private function columnas(): array
    {
        return [
            'fecha_venta' => Esquema::resolver('ventas_credito', ['fecha_venta', 'fecha']),
            'numero_vale' => Esquema::resolver('ventas_credito', ['numero_vale', 'numero_comprobante', 'vale_numero']),
            'observaciones' => Esquema::resolver('ventas_credito', ['observaciones']),
            'cantidad' => Esquema::resolver('detalle_ventas_credito', ['cantidad_galones', 'cantidad_gal', 'galones', 'cantidad']),
            'precio' => Esquema::resolver('detalle_ventas_credito', ['precio_unitario', 'precio']),
            'importe' => Esquema::resolver('detalle_ventas_credito', ['importe', 'monto', 'total']),
            'fecha_pago' => Esquema::resolver('pagos_credito', ['fecha_pago', 'fecha']),
            'monto_pago' => Esquema::resolver('pagos_credito', ['monto', 'importe']),
            'referencia_pago' => Esquema::resolver('pagos_credito', ['referencia']),
            'observaciones_pago' => Esquema::resolver('pagos_credito', ['observaciones']),
        ];
    }

    public function indice(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'OPERADOR', 'CONTABILIDAD', 'LECTURA']);
        $c = $this->columnas();

        $q = DB::table('ventas_credito as v')
            ->join('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->where('v.establecimiento_id', $this->establecimientoId())
            ->select('v.*', 'c.nombre as cliente');

        if ($this->esOperador()) {
            $trabajadorId = $this->exigirTrabajadorVinculado();
            $q->whereIn('v.turno_operativo_id', function ($sub) use ($trabajadorId) {
                $sub->select('id')
                    ->from('turnos_operativos')
                    ->where('establecimiento_id', $this->establecimientoId())
                    ->where('trabajador_id', $trabajadorId);
            });
        }

        $desde = $request->query('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->query('hasta', now()->toDateString());
        $cliente = $request->query('cliente');

        if ($c['fecha_venta'] && $desde) {
            $q->whereDate('v.' . $c['fecha_venta'], '>=', $desde);
        }
        if ($c['fecha_venta'] && $hasta) {
            $q->whereDate('v.' . $c['fecha_venta'], '<=', $hasta);
        }
        if ($cliente) {
            $q->where('v.cliente_id', $cliente);
        }

        $ventas = $q
            ->orderByDesc($c['fecha_venta'] ? 'v.' . $c['fecha_venta'] : 'v.id')
            ->orderByDesc('v.id')
            ->paginate(10)
            ->withQueryString();

        $clientes = DB::table('clientes')
            ->where('establecimiento_id', $this->establecimientoId())
            ->orderBy('nombre')
            ->get();

        $totales = [];
        foreach ($ventas as $v) {
            $items = DB::table('detalle_ventas_credito')
                ->where('venta_credito_id', $v->id)
                ->get();

            $totales[$v->id] = $items->sum(function ($i) use ($c) {
                if ($c['importe']) {
                    return (float) ($i->{$c['importe']} ?? 0);
                }

                return (float) ($i->{$c['cantidad']} ?? 0)
                    * (float) ($i->{$c['precio']} ?? 0);
            });
        }

        $esOperador = $this->esOperador();
        $saldoClientes = $esOperador ? [] : $this->saldosClientes($clientes, $c);

        return view('creditos.indice', compact(
            'ventas',
            'clientes',
            'totales',
            'saldoClientes',
            'desde',
            'hasta',
            'cliente',
            'c',
            'esOperador'
        ));
    }

    public function crear(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'OPERADOR', 'CONTABILIDAD']);

        $clientes = DB::table('clientes')
            ->where('establecimiento_id', $this->establecimientoId())
            ->orderBy('nombre')
            ->get();

        // Las ventas por galón a crédito corresponden a combustibles.
        $productos = DB::table('productos')
            ->where('categoria', 'COMBUSTIBLE')
            ->where('activo', 1)
            ->orderBy('id')
            ->get();

        $turnosQuery = DB::table('turnos_operativos as t')
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->leftJoin('trabajadores as tr', 'tr.id', '=', 't.trabajador_id')
            ->where('t.establecimiento_id', $this->establecimientoId());

        $this->aplicarAlcanceTurnosOperador($turnosQuery, 't');
        PermisosSupervisor::filtrarTurnosEditables($turnosQuery);

        $turnos = $turnosQuery
            ->orderByDesc('t.fecha_operacion')
            ->orderByDesc('t.id')
            ->limit(500)
            ->get([
                't.id',
                't.fecha_operacion',
                'tt.codigo',
                'tt.nombre',
                'tr.nombre_completo as trabajador',
            ]);

        $puedeModificarPrecio = auth()->user()?->rol === 'ADMINISTRADOR';
        $turnoPreseleccionado = $request->integer('turno_operativo_id') ?: null;
        if ($turnoPreseleccionado && ! $turnos->contains('id', $turnoPreseleccionado)) {
            $turnoPreseleccionado = null;
        }

        return view('creditos.formulario', compact(
            'clientes',
            'productos',
            'turnos',
            'puedeModificarPrecio',
            'turnoPreseleccionado'
        ));
    }

    public function precioVigente(Request $request, ServicioPrecioVenta $servicioPrecio)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'OPERADOR', 'CONTABILIDAD']);

        $datos = $request->validate([
            'turno_operativo_id' => ['required', 'integer', Rule::exists('turnos_operativos', 'id')],
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
        ]);

        $turno = DB::table('turnos_operativos')
            ->where('id', $datos['turno_operativo_id'])
            ->where('establecimiento_id', $this->establecimientoId())
            ->first();

        abort_unless($turno, 404);
        if ($this->esOperador()) {
            abort_unless((int)$turno->trabajador_id === $this->exigirTrabajadorVinculado(), 404);
        }

        $precio = $servicioPrecio->obtenerParaTurno(
            $this->establecimientoId(),
            (int) $datos['producto_id'],
            (int) $datos['turno_operativo_id']
        );

        if (! $precio) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No existe un precio de venta registrado para este producto en el turno seleccionado.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'precio' => number_format((float) $precio->precio, 2, '.', ''),
            'fecha_operacion' => $precio->fecha_operacion,
            'turno' => $precio->codigo_turno,
            'historial_precio_id' => $precio->historial_precio_id,
            'turno_precio_desde_id' => $precio->turno_operativo_desde_id,
        ]);
    }

    public function guardar(Request $request, ServicioPrecioVenta $servicioPrecio)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'OPERADOR', 'CONTABILIDAD']);

        $request->validate([
            'cliente_id' => ['required', Rule::exists('clientes', 'id')],
            'turno_operativo_id' => ['required', Rule::exists('turnos_operativos', 'id')],
            'numero_vale' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', Rule::exists('productos', 'id')],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_manual' => ['nullable', 'numeric', 'gt:0', 'max:9999.9999'],
        ]);

        $cliente = DB::table('clientes')
            ->where('id', $request->cliente_id)
            ->where('establecimiento_id', $this->establecimientoId())
            ->first();

        abort_unless($cliente, 404);

        $turno = DB::table('turnos_operativos')
            ->where('id', $request->turno_operativo_id)
            ->where('establecimiento_id', $this->establecimientoId())
            ->first();

        abort_unless($turno, 404);
        PermisosSupervisor::exigirTurnoEditable((int)$turno->id);
        if ($this->esOperador()) {
            abort_unless((int)$turno->trabajador_id === $this->exigirTrabajadorVinculado(), 404);
        }

        $c = $this->columnas();
        $detalles = [];
        $puedeModificarPrecio = auth()->user()?->rol === 'ADMINISTRADOR';

        foreach ($request->items as $indice => $item) {
            $producto = DB::table('productos')
                ->where('id', $item['producto_id'])
                ->where('categoria', 'COMBUSTIBLE')
                ->where('activo', 1)
                ->first();

            if (! $producto) {
                throw ValidationException::withMessages([
                    "items.$indice.producto_id" => 'El producto seleccionado no es un combustible activo.',
                ]);
            }

            $precio = $servicioPrecio->obtenerParaTurno(
                $this->establecimientoId(),
                (int) $item['producto_id'],
                (int) $request->turno_operativo_id
            );

            if (! $precio) {
                throw ValidationException::withMessages([
                    "items.$indice.producto_id" => sprintf(
                        'No existe precio de venta para %s en el turno seleccionado.',
                        $producto->nombre
                    ),
                ]);
            }

            $cantidad = round((float) $item['cantidad'], 4);
            $precioAutomatico = round((float) $precio->precio, 4);
            $precioUnitario = $precioAutomatico;
            $precioModificadoManual = false;

            $precioManualSolicitado = array_key_exists('precio_manual', $item)
                && $item['precio_manual'] !== null
                && $item['precio_manual'] !== ''
                    ? round((float) $item['precio_manual'], 4)
                    : null;

            if ($precioManualSolicitado !== null) {
                if (! $puedeModificarPrecio) {
                    throw ValidationException::withMessages([
                        "items.$indice.precio_manual" =>
                            'Solo un usuario con rol ADMINISTRADOR puede modificar el precio calculado automáticamente.',
                    ]);
                }

                $precioUnitario = $precioManualSolicitado;
                $precioModificadoManual = abs($precioUnitario - $precioAutomatico) > 0.00001;
            }

            $importe = round($cantidad * $precioUnitario, 2);

            $detalles[] = [
                'producto_id' => (int) $item['producto_id'],
                'producto' => $producto->nombre,
                'cantidad' => $cantidad,
                'precio_automatico' => $precioAutomatico,
                'precio' => $precioUnitario,
                'precio_modificado_manual' => $precioModificadoManual,
                'importe' => $importe,
                'historial_precio_id' => $precio->historial_precio_id,
            ];
        }

        if (count($detalles) === 0) {
            throw ValidationException::withMessages([
                'items' => 'Seleccione al menos un combustible e ingrese una cantidad mayor que cero.',
            ]);
        }

        $id = DB::transaction(function () use ($request, $c, $turno, $detalles) {
            PermisosSupervisor::exigirTurnoEditable((int) $turno->id, true);

            $cab = [
                'establecimiento_id' => $this->establecimientoId(),
                'cliente_id' => $request->cliente_id,
                'turno_operativo_id' => (int) $request->turno_operativo_id,
                'hoja_origen' => 'SISTEMA',
                'creado_en' => now(),
            ];

            // La fecha de la venta se toma del turno operativo real seleccionado.
            if ($c['fecha_venta']) {
                $cab[$c['fecha_venta']] = $turno->fecha_operacion;
            }
            if ($c['numero_vale']) {
                $cab[$c['numero_vale']] = $request->numero_vale ?: null;
            }
            if ($c['observaciones']) {
                $cab[$c['observaciones']] = $request->observaciones ?: null;
            }

            $id = DB::table('ventas_credito')
                ->insertGetId(Esquema::filtrar('ventas_credito', $cab));

            foreach ($detalles as $detalle) {
                $fila = [
                    'venta_credito_id' => $id,
                    'producto_id' => $detalle['producto_id'],
                ];

                if ($c['cantidad']) {
                    $fila[$c['cantidad']] = $detalle['cantidad'];
                }
                if ($c['precio']) {
                    $fila[$c['precio']] = $detalle['precio'];
                }
                if ($c['importe']) {
                    $fila[$c['importe']] = $detalle['importe'];
                }

                DB::table('detalle_ventas_credito')
                    ->insert(Esquema::filtrar('detalle_ventas_credito', $fila));
            }

            ServicioAuditoria::registrar(
                'VENTA_CREDITO',
                $id,
                'CREAR',
                null,
                [
                    'cabecera' => $cab,
                    'detalle' => $detalles,
                ],
                $request
            );

            app(\App\Services\ServicioFinancieroTurno::class)->sincronizar((int) $turno->id);

            return $id;
        }, 3);

        $huboPrecioManual = collect($detalles)
            ->contains(fn (array $detalle) => $detalle['precio_modificado_manual']);

        if ($request->filled('volver_turno_id')) {
            $turnoRetorno = (int) $request->input('volver_turno_id');

            if ($turnoRetorno > 0) {
                return redirect()->route('turnos.mostrar', $turnoRetorno)
                    ->with('exito', 'Credito registrado. El detalle financiero del turno fue recalculado.');
            }
        }

        return redirect()
            ->route('creditos.mostrar', $id)
            ->with(
                'exito',
                $huboPrecioManual
                    ? 'Venta a crédito registrada. El administrador modificó al menos un precio automático; el cambio quedó registrado en auditoría.'
                    : 'Venta a crédito registrada con el precio vigente del turno seleccionado.'
            );
    }

    public function mostrar(int $credito)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'OPERADOR', 'CONTABILIDAD', 'LECTURA']);
        $c = $this->columnas();

        $venta = DB::table('ventas_credito as v')
            ->join('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->leftJoin('turnos_operativos as t', 't.id', '=', 'v.turno_operativo_id')
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->where('v.id', $credito)
            ->where('v.establecimiento_id', $this->establecimientoId())
            ->select('v.*', 'c.nombre as cliente', 't.fecha_operacion', 'tt.codigo as turno_codigo')
            ->first();

        abort_unless($venta, 404);
        if ($this->esOperador()) {
            $turnoVenta = DB::table('turnos_operativos')->where('id',$venta->turno_operativo_id)->first();
            abort_unless($turnoVenta && (int)$turnoVenta->trabajador_id === $this->exigirTrabajadorVinculado(), 404);
        }

        $items = DB::table('detalle_ventas_credito as d')
            ->leftJoin('productos as p', 'p.id', '=', 'd.producto_id')
            ->where('d.venta_credito_id', $credito)
            ->select('d.*', 'p.codigo as producto_codigo', 'p.nombre as producto')
            ->get();

        $total = $items->sum(function ($i) use ($c) {
            if ($c['importe']) {
                return (float) ($i->{$c['importe']} ?? 0);
            }

            return (float) ($i->{$c['cantidad']} ?? 0)
                * (float) ($i->{$c['precio']} ?? 0);
        });

        return view('creditos.mostrar', compact('venta', 'items', 'total', 'c'));
    }

    public function editar(int $credito)
    {
        $this->autorizar(['ADMINISTRADOR']);
        $c = $this->columnas();

        $venta = DB::table('ventas_credito')
            ->where('id', $credito)
            ->where('establecimiento_id', $this->establecimientoId())
            ->first();

        abort_unless($venta, 404);

        $items = DB::table('detalle_ventas_credito')
            ->where('venta_credito_id', $credito)
            ->get();

        $itemsEdicion = [];
        foreach ($items as $item) {
            $productoId = (int) $item->producto_id;
            $itemsEdicion[$productoId] = [
                'cantidad' => $c['cantidad'] ? (float) ($item->{$c['cantidad']} ?? 0) : 0,
                'precio' => $c['precio'] ? (float) ($item->{$c['precio']} ?? 0) : 0,
                'importe' => $c['importe'] ? (float) ($item->{$c['importe']} ?? 0) : null,
            ];
        }

        $clientes = DB::table('clientes')
            ->where('establecimiento_id', $this->establecimientoId())
            ->orderBy('nombre')
            ->get();

        $productosActuales = collect(array_keys($itemsEdicion));
        $productos = DB::table('productos')
            ->where('categoria', 'COMBUSTIBLE')
            ->where(function ($consulta) use ($productosActuales) {
                $consulta->where('activo', 1);
                if ($productosActuales->isNotEmpty()) {
                    $consulta->orWhereIn('id', $productosActuales->all());
                }
            })
            ->orderBy('id')
            ->get();

        // En edición se muestran todos los turnos para poder conservar créditos históricos.
        $turnos = DB::table('turnos_operativos as t')
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->leftJoin('trabajadores as tr', 'tr.id', '=', 't.trabajador_id')
            ->where('t.establecimiento_id', $this->establecimientoId())
            ->orderByDesc('t.fecha_operacion')
            ->orderByDesc('t.id')
            ->get([
                't.id',
                't.fecha_operacion',
                'tt.codigo',
                'tt.nombre',
                'tr.nombre_completo as trabajador',
            ]);

        $puedeModificarPrecio = true;

        return view('creditos.editar', compact(
            'venta',
            'itemsEdicion',
            'clientes',
            'productos',
            'turnos',
            'puedeModificarPrecio',
            'c'
        ));
    }

    public function actualizar(Request $request, int $credito, ServicioPrecioVenta $servicioPrecio)
    {
        $this->autorizar(['ADMINISTRADOR']);

        $request->validate([
            'cliente_id' => ['required', Rule::exists('clientes', 'id')],
            'turno_operativo_id' => ['required', Rule::exists('turnos_operativos', 'id')],
            'numero_vale' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', Rule::exists('productos', 'id')],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_manual' => ['nullable', 'numeric', 'gt:0', 'max:9999.9999'],
        ]);

        $ventaAnterior = DB::table('ventas_credito')
            ->where('id', $credito)
            ->where('establecimiento_id', $this->establecimientoId())
            ->first();

        abort_unless($ventaAnterior, 404);

        $cliente = DB::table('clientes')
            ->where('id', $request->cliente_id)
            ->where('establecimiento_id', $this->establecimientoId())
            ->first();

        abort_unless($cliente, 404);

        $turno = DB::table('turnos_operativos')
            ->where('id', $request->turno_operativo_id)
            ->where('establecimiento_id', $this->establecimientoId())
            ->first();

        abort_unless($turno, 404);

        $c = $this->columnas();
        $detallesAnteriores = DB::table('detalle_ventas_credito')
            ->where('venta_credito_id', $credito)
            ->get();
        $productosAnteriores = $detallesAnteriores
            ->pluck('producto_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $detalles = [];

        foreach ($request->items as $indice => $item) {
            $productoId = (int) $item['producto_id'];
            $producto = DB::table('productos')
                ->where('id', $productoId)
                ->where('categoria', 'COMBUSTIBLE')
                ->first();

            if (! $producto || (! (bool) $producto->activo && ! in_array($productoId, $productosAnteriores, true))) {
                throw ValidationException::withMessages([
                    "items.$indice.producto_id" => 'El producto seleccionado no es un combustible habilitado para este crédito.',
                ]);
            }

            $precio = $servicioPrecio->obtenerParaTurno(
                $this->establecimientoId(),
                $productoId,
                (int) $request->turno_operativo_id
            );

            if (! $precio) {
                throw ValidationException::withMessages([
                    "items.$indice.producto_id" => sprintf(
                        'No existe precio de venta para %s en el turno seleccionado.',
                        $producto->nombre
                    ),
                ]);
            }

            $cantidad = round((float) $item['cantidad'], 4);
            $precioAutomatico = round((float) $precio->precio, 4);
            $precioUnitario = $precioAutomatico;
            $precioModificadoManual = false;

            $precioManualSolicitado = array_key_exists('precio_manual', $item)
                && $item['precio_manual'] !== null
                && $item['precio_manual'] !== ''
                    ? round((float) $item['precio_manual'], 4)
                    : null;

            if ($precioManualSolicitado !== null) {
                $precioUnitario = $precioManualSolicitado;
                $precioModificadoManual = abs($precioUnitario - $precioAutomatico) > 0.00001;
            }

            $importe = round($cantidad * $precioUnitario, 2);

            $detalles[] = [
                'producto_id' => $productoId,
                'producto' => $producto->nombre,
                'cantidad' => $cantidad,
                'precio_automatico' => $precioAutomatico,
                'precio' => $precioUnitario,
                'precio_modificado_manual' => $precioModificadoManual,
                'importe' => $importe,
                'historial_precio_id' => $precio->historial_precio_id,
            ];
        }

        if (count($detalles) === 0) {
            throw ValidationException::withMessages([
                'items' => 'Seleccione al menos un combustible e ingrese una cantidad mayor que cero.',
            ]);
        }

        $turnoAnteriorId = (int) ($ventaAnterior->turno_operativo_id ?? 0);
        $antes = [
            'cabecera' => (array) $ventaAnterior,
            'detalle' => $detallesAnteriores->map(fn ($fila) => (array) $fila)->all(),
        ];

        DB::transaction(function () use ($request, $credito, $c, $turno, $detalles, $antes) {
            // Bloquea la cabecera durante la modificación para evitar escrituras concurrentes.
            $ventaBloqueada = DB::table('ventas_credito')
                ->where('id', $credito)
                ->where('establecimiento_id', $this->establecimientoId())
                ->lockForUpdate()
                ->first();

            abort_unless($ventaBloqueada, 404);

            $cab = [
                'cliente_id' => (int) $request->cliente_id,
                'turno_operativo_id' => (int) $request->turno_operativo_id,
            ];

            if ($c['fecha_venta']) {
                $cab[$c['fecha_venta']] = $turno->fecha_operacion;
            }
            if ($c['numero_vale']) {
                $cab[$c['numero_vale']] = $request->numero_vale ?: null;
            }
            if ($c['observaciones']) {
                $cab[$c['observaciones']] = $request->observaciones ?: null;
            }

            DB::table('ventas_credito')
                ->where('id', $credito)
                ->where('establecimiento_id', $this->establecimientoId())
                ->update(Esquema::filtrar('ventas_credito', $cab));

            DB::table('detalle_ventas_credito')
                ->where('venta_credito_id', $credito)
                ->delete();

            foreach ($detalles as $detalle) {
                $fila = [
                    'venta_credito_id' => $credito,
                    'producto_id' => $detalle['producto_id'],
                ];

                if ($c['cantidad']) {
                    $fila[$c['cantidad']] = $detalle['cantidad'];
                }
                if ($c['precio']) {
                    $fila[$c['precio']] = $detalle['precio'];
                }
                if ($c['importe']) {
                    $fila[$c['importe']] = $detalle['importe'];
                }

                DB::table('detalle_ventas_credito')
                    ->insert(Esquema::filtrar('detalle_ventas_credito', $fila));
            }

            $ventaDespues = DB::table('ventas_credito')
                ->where('id', $credito)
                ->first();

            ServicioAuditoria::registrar(
                'VENTA_CREDITO',
                $credito,
                'ACTUALIZAR',
                $antes,
                [
                    'cabecera' => (array) $ventaDespues,
                    'detalle' => $detalles,
                ],
                $request
            );
        });

        $turnosARecalcular = collect([
            $turnoAnteriorId,
            (int) $request->turno_operativo_id,
        ])->filter()->unique();

        if (class_exists(\App\Services\ServicioFinancieroTurno::class)) {
            foreach ($turnosARecalcular as $turnoId) {
                app(\App\Services\ServicioFinancieroTurno::class)->sincronizar((int) $turnoId);
            }
        }

        return redirect()
            ->route('creditos.mostrar', $credito)
            ->with('exito', 'Crédito actualizado correctamente. El cambio quedó registrado en auditoría.');
    }

    public function guardarPago(Request $request, int $cliente)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD']);
        $c = $this->columnas();

        $request->validate([
            'fecha_pago' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'medio_pago_id' => ['nullable', Rule::exists('medios_pago', 'id')],
            'referencia' => ['nullable', 'string', 'max:120'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ]);

        $cli = DB::table('clientes')
            ->where('id', $cliente)
            ->where('establecimiento_id', $this->establecimientoId())
            ->first();

        abort_unless($cli, 404);

        $datos = [
            'cliente_id' => $cliente,
            'medio_pago_id' => $request->medio_pago_id ?: null,
        ];

        if ($c['fecha_pago']) {
            $datos[$c['fecha_pago']] = $request->fecha_pago;
        }
        if ($c['monto_pago']) {
            $datos[$c['monto_pago']] = (float) $request->monto;
        }
        if ($c['referencia_pago']) {
            $datos[$c['referencia_pago']] = $request->referencia ?: null;
        }
        if ($c['observaciones_pago']) {
            $datos[$c['observaciones_pago']] = $request->observaciones ?: null;
        }

        $id = DB::table('pagos_credito')
            ->insertGetId(Esquema::filtrar('pagos_credito', $datos));

        ServicioAuditoria::registrar('PAGO_CREDITO', $id, 'CREAR', null, $datos, $request);

        return back()->with('exito', 'Pago registrado correctamente.');
    }

    private function saldosClientes($clientes, array $c): array
    {
        $s = [];

        foreach ($clientes as $cli) {
            $ventas = DB::table('ventas_credito')
                ->where('cliente_id', $cli->id)
                ->pluck('id');

            $deuda = 0;
            if ($ventas->count()) {
                $items = DB::table('detalle_ventas_credito')
                    ->whereIn('venta_credito_id', $ventas)
                    ->get();

                $deuda = $items->sum(function ($i) use ($c) {
                    if ($c['importe']) {
                        return (float) ($i->{$c['importe']} ?? 0);
                    }

                    return (float) ($i->{$c['cantidad']} ?? 0)
                        * (float) ($i->{$c['precio']} ?? 0);
                });
            }

            $pagos = $c['monto_pago']
                ? (float) DB::table('pagos_credito')->where('cliente_id', $cli->id)->sum($c['monto_pago'])
                : 0;

            $s[$cli->id] = [
                'ventas' => $deuda,
                'pagos' => $pagos,
                'saldo' => $deuda - $pagos,
            ];
        }

        return $s;
    }
}
