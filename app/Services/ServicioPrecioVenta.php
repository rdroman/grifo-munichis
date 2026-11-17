<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ServicioPrecioVenta
{
    /**
     * Obtiene el precio de venta que regía para un producto en un turno operativo.
     *
     * Regla principal:
     * - Si el historial de precios está vinculado a un turno mediante
     *   turno_operativo_desde_id, se respeta la secuencia real de turnos/cortes.
     * - No se asume T1 -> T2 -> T3 ni que T4 sea siempre cambio de precio.
     * - Dentro de una misma fecha_operacion, el id del turno operativo actúa como
     *   desempate de secuencia real registrada.
     * - Para registros históricos antiguos sin turno vinculado, se usa vigente_desde
     *   como mecanismo de compatibilidad.
     */
    public function obtenerParaTurno(
        int $establecimientoId,
        int $productoId,
        int $turnoOperativoId
    ): ?object {
        $turno = DB::table('turnos_operativos as t')
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->where('t.id', $turnoOperativoId)
            ->where('t.establecimiento_id', $establecimientoId)
            ->select(
                't.id',
                't.fecha_operacion',
                't.tipo_turno_id',
                'tt.codigo as codigo_turno',
                'tt.hora_inicio'
            )
            ->first();

        if (! $turno) {
            return null;
        }

        $horaInicio = $turno->hora_inicio ?: '23:59:59';
        $momentoTurno = Carbon::parse($turno->fecha_operacion . ' ' . $horaInicio);

        $precio = DB::table('historial_precios as h')
            ->leftJoin('turnos_operativos as td', 'td.id', '=', 'h.turno_operativo_desde_id')
            ->where('h.establecimiento_id', $establecimientoId)
            ->where('h.producto_id', $productoId)
            ->where('h.tipo_precio', 'VENTA')
            ->where(function ($q) use ($turno, $momentoTurno) {
                $q->where(function ($qConTurno) use ($turno) {
                    $qConTurno
                        ->whereNotNull('h.turno_operativo_desde_id')
                        ->where(function ($qSecuencia) use ($turno) {
                            $qSecuencia
                                ->where('td.fecha_operacion', '<', $turno->fecha_operacion)
                                ->orWhere(function ($qMismaFecha) use ($turno) {
                                    $qMismaFecha
                                        ->where('td.fecha_operacion', '=', $turno->fecha_operacion)
                                        ->where('td.id', '<=', $turno->id);
                                });
                        });
                })
                ->orWhere(function ($qLegacy) use ($momentoTurno) {
                    $qLegacy
                        ->whereNull('h.turno_operativo_desde_id')
                        ->where('h.vigente_desde', '<=', $momentoTurno);
                });
            })
            ->select(
                'h.id as historial_precio_id',
                'h.producto_id',
                'h.precio',
                'h.vigente_desde',
                'h.vigente_hasta',
                'h.turno_operativo_desde_id',
                'td.fecha_operacion as fecha_turno_desde'
            )
            ->orderByRaw('CASE WHEN h.turno_operativo_desde_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('td.fecha_operacion')
            ->orderByDesc('td.id')
            ->orderByDesc('h.vigente_desde')
            ->orderByDesc('h.id')
            ->first();

        if (! $precio) {
            return null;
        }

        $precio->fecha_operacion = $turno->fecha_operacion;
        $precio->codigo_turno = $turno->codigo_turno;
        $precio->turno_operativo_id = $turno->id;

        return $precio;
    }
}
