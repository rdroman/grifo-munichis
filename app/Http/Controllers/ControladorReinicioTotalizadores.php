<?php

namespace App\Http\Controllers;

use App\Services\ServicioAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ControladorReinicioTotalizadores extends ControladorBaseGrifo
{
    public function guardar(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR']);

        $datos = $request->validate([
            'productos' => ['required','array','min:1'],
            'productos.*' => [
                'required',
                'integer',
                Rule::exists('productos', 'id'),
            ],
            'motivo' => ['required','string','max:500'],
            'confirmacion' => ['required','accepted'],
        ]);

        $productoIds = collect($datos['productos'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($request, $datos, $productoIds) {
            $ultimoTurno = DB::table('turnos_operativos')
                ->where('establecimiento_id', $this->establecimientoId())
                ->orderByDesc('fecha_operacion')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $ultimoTurno) {
                throw ValidationException::withMessages([
                    'productos' =>
                        'Debe existir al menos un turno registrado antes de registrar un reinicio de totalizadores.',
                ]);
            }

            $pendiente = DB::table('reinicios_totalizador')
                ->where('establecimiento_id', $this->establecimientoId())
                ->where('estado', 'PENDIENTE')
                ->lockForUpdate()
                ->first();

            if ($pendiente) {
                throw ValidationException::withMessages([
                    'productos' =>
                        'Ya existe un reinicio pendiente. Debe aplicarse al siguiente turno antes de registrar otro.',
                ]);
            }

            $mangueras = DB::table('mangueras as m')
                ->join('surtidores as s', 's.id', '=', 'm.surtidor_id')
                ->join('productos as p', 'p.id', '=', 'm.producto_id')
                ->where('s.establecimiento_id', $this->establecimientoId())
                ->whereIn('m.producto_id', $productoIds->all())
                ->where(function ($q) {
                    $q->whereNull('m.activo')->orWhere('m.activo', 1);
                })
                ->where(function ($q) {
                    $q->whereNull('s.activo')->orWhere('s.activo', 1);
                })
                ->orderBy('m.id')
                ->get([
                    'm.id',
                    'm.codigo',
                    'm.lado',
                    'm.producto_id',
                    'p.codigo as producto_codigo',
                    'p.nombre as producto',
                ]);

            $productosEncontrados = $mangueras
                ->pluck('producto_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($productosEncontrados->count() !== $productoIds->count()) {
                throw ValidationException::withMessages([
                    'productos' =>
                        'Uno o más productos seleccionados no tienen mangueras activas en este establecimiento.',
                ]);
            }

            $reinicioId = DB::table('reinicios_totalizador')->insertGetId([
                'establecimiento_id' => $this->establecimientoId(),
                'usuario_id' => auth()->id(),
                'turno_anterior_id' => (int) $ultimoTurno->id,
                'turno_operativo_id' => null,
                'estado' => 'PENDIENTE',
                'motivo' => trim((string) $datos['motivo']),
                'confirmado_en' => now('America/Lima'),
                'aplicado_en' => null,
                'creado_en' => now('America/Lima'),
            ]);

            $detalleAuditoria = [];

            foreach ($mangueras as $manguera) {
                $ultimaLectura = DB::table('lecturas_totalizador as l')
                    ->join(
                        'turnos_operativos as t',
                        't.id',
                        '=',
                        'l.turno_operativo_id'
                    )
                    ->where(
                        't.establecimiento_id',
                        $this->establecimientoId()
                    )
                    ->where(
                        'l.manguera_id',
                        $manguera->id
                    )
                    ->orderByDesc('t.fecha_operacion')
                    ->orderByDesc('t.id')
                    ->orderByDesc('l.id')
                    ->value('l.lectura');

                if ($ultimaLectura === null) {
                    throw ValidationException::withMessages([
                        'productos' =>
                            'No existe una lectura anterior para la manguera '
                            . $manguera->codigo
                            . '. No se puede registrar el reinicio.',
                    ]);
                }

                $lecturaAnterior = round((float) $ultimaLectura, 3);

                DB::table('reinicios_totalizador_detalle')->insert([
                    'reinicio_totalizador_id' => $reinicioId,
                    'producto_id' => (int) $manguera->producto_id,
                    'manguera_id' => (int) $manguera->id,
                    'lectura_anterior' => $lecturaAnterior,
                    'lectura_base' => 0.000,
                    'creado_en' => now('America/Lima'),
                ]);

                $detalleAuditoria[] = [
                    'producto_id' => (int) $manguera->producto_id,
                    'producto' => (string) $manguera->producto_codigo,
                    'manguera_id' => (int) $manguera->id,
                    'manguera' => (string) $manguera->codigo,
                    'lado' => $manguera->lado,
                    'lectura_anterior' => $lecturaAnterior,
                    'lectura_base' => 0.000,
                ];
            }

            ServicioAuditoria::registrar(
                'REINICIO_TOTALIZADOR',
                $reinicioId,
                'CREAR',
                null,
                [
                    'estado' => 'PENDIENTE',
                    'turno_anterior_id' => (int) $ultimoTurno->id,
                    'productos' => $productoIds->all(),
                    'motivo' => trim((string) $datos['motivo']),
                    'detalle' => $detalleAuditoria,
                ],
                $request
            );
        });

        return redirect()
            ->route('turnos.indice')
            ->with(
                'exito',
                'Reinicio registrado. Los productos seleccionados usarán 0.000 como base en el siguiente turno o corte que se registre.'
            );
    }
}
