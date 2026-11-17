<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ControladorPanel extends Controller
{
    public function indice()
    {
        // PARCHE_UI_REPORTES_CAJA_20260925 - dashboard operativo y limitado para SUPERVISOR.
        if (auth()->user()->rol === 'SUPERVISOR') {
            return app(\App\Http\Controllers\ControladorPanelSupervisor::class)->indice();
        }

        if (auth()->user()->rol === 'OPERADOR') {
            return redirect()->route('turnos.indice');
        }

        $hoy = now()->toDateString();

        $ultimaFechaDisponible = DB::table('vw_ventas_diarias')
            ->max('fecha_operacion');

        $hayDatosHoy = DB::table('vw_ventas_diarias')
            ->whereDate('fecha_operacion', $hoy)
            ->exists();

        $fechaReferencia = $hayDatosHoy
            ? $hoy
            : $ultimaFechaDisponible;

        $resumenDia = null;
        $galonesDia = 0.0;
        $galonesPorProductoDia = collect();
        $diferenciaDia = 0.0;

        if ($fechaReferencia) {
            $resumenDia = DB::table('vw_ventas_diarias')
                ->whereDate('fecha_operacion', $fechaReferencia)
                ->first();

            $galonesPorProductoDia = DB::table('totales_producto_turno as tp')
                ->join('turnos_operativos as t', 't.id', '=', 'tp.turno_operativo_id')
                ->join('productos as p', 'p.id', '=', 'tp.producto_id')
                ->whereDate('t.fecha_operacion', $fechaReferencia)
                ->where('p.categoria', 'COMBUSTIBLE')
                ->groupBy('p.id', 'p.codigo', 'p.nombre')
                ->orderBy('p.id')
                ->get([
                    'p.codigo',
                    'p.nombre',
                    DB::raw('SUM(COALESCE(tp.galones,0)) as galones'),
                ]);

            $galonesDia = (float) $galonesPorProductoDia->sum('galones');

            $financierosDia = DB::table('turnos_operativos as t')
                ->join('resumen_financiero_turno as rf', 'rf.turno_operativo_id', '=', 't.id')
                ->whereDate('t.fecha_operacion', $fechaReferencia)
                ->select(
                    'rf.neto_esperado',
                    'rf.efectivo_entregado',
                    'rf.digital_operador',
                    'rf.efectivo_administrador',
                    'rf.digital_administrador',
                    'rf.validado_efectivo_en',
                    'rf.estado_validacion_digital'
                )
                ->get();

            $diferenciaDia = round(
                $financierosDia->sum(function ($rf) {
                    return $this->resolverConciliacion($rf)['diferencia'];
                }),
                2
            );
        }

        $metricas = [
            'ventas_brutas' => (float) ($resumenDia->ventas_brutas ?? 0),
            'gastos_efectivo' => (float) ($resumenDia->gastos_efectivo ?? 0),
            'neto_esperado' => (float) ($resumenDia->neto_esperado ?? 0),
            'galones' => $galonesDia,
            'efectivo_entregado' => (float) ($resumenDia->efectivo_entregado ?? 0),
            'digital' => (float) (
                $resumenDia->digital_administrador
                ?? $resumenDia->digital_operador
                ?? 0
            ),
            'diferencia' => $diferenciaDia,
        ];

        $preciosVigentes = DB::table('vw_precios_venta_vigentes as pv')
            ->join('productos as p', 'p.id', '=', 'pv.producto_id')
            ->where('p.activo', 1)
            ->where('p.categoria', 'COMBUSTIBLE')
            ->select(
                'pv.producto_id',
                'pv.codigo_producto',
                'pv.nombre_producto',
                'pv.precio',
                'pv.vigente_desde'
            )
            ->orderBy('pv.producto_id')
            ->get();

        $ultimosTurnos = DB::table('turnos_operativos as t')
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->leftJoin('trabajadores as tr', 'tr.id', '=', 't.trabajador_id')
            ->leftJoin('resumen_financiero_turno as rf', 'rf.turno_operativo_id', '=', 't.id')
            ->select(
                't.id as turno_operativo_id',
                't.fecha_operacion',
                'tt.codigo as codigo_turno',
                'tr.nombre_completo as trabajador',
                'rf.ventas_brutas_combustible',
                'rf.gastos_efectivo',
                'rf.neto_esperado',
                'rf.efectivo_entregado',
                'rf.digital_operador',
                'rf.efectivo_administrador',
                'rf.digital_administrador',
                'rf.validado_efectivo_en',
                'rf.estado_validacion_digital'
            )
            ->orderByDesc('t.fecha_operacion')
            ->orderByDesc('t.id')
            ->limit(8)
            ->get();

        $ultimosTurnos->transform(function ($turno) {
            $conciliacion = $this->resolverConciliacion($turno);
            $turno->diferencia_caja = $conciliacion['diferencia'];
            return $turno;
        });

        $ultimoTurno = $ultimosTurnos->first();

        $trabajadoresActivos = DB::table('trabajadores')
            ->where('activo', 1)
            ->count();

        $validacionesPendientes = DB::table('resumen_financiero_turno')
            ->where('estado_validacion_digital', 'PENDIENTE')
            ->count();

        $ventasUltimos7Dias = DB::table('vw_ventas_diarias')
            ->select('fecha_operacion', 'ventas_brutas')
            ->orderByDesc('fecha_operacion')
            ->limit(7)
            ->get()
            ->sortBy('fecha_operacion')
            ->values();

        return view('panel', compact(
            'fechaReferencia',
            'hayDatosHoy',
            'metricas',
            'galonesPorProductoDia',
            'preciosVigentes',
            'ultimosTurnos',
            'ultimoTurno',
            'trabajadoresActivos',
            'validacionesPendientes',
            'ventasUltimos7Dias'
        ));
    }

    /**
     * Aplica la misma regla de conciliación usada en el detalle/listado de turnos.
     *
     * - Antes de validación completa: usa efectivo + digital informados por el operador.
     *   Si alguno es NULL/0, usa el valor administrativo del mismo componente.
     * - Después de validar efectivo y digital: usa efectivo + digital administrativos,
     *   con fallback al valor del operador si el administrativo fuera NULL.
     */
    private function resolverConciliacion(object $rf): array
    {
        $montoAEntregar = round((float) ($rf->neto_esperado ?? 0), 2);

        $efectivoOperador = (float) ($rf->efectivo_entregado ?? 0);
        $digitalOperador = (float) ($rf->digital_operador ?? 0);

        $efectivoAdmin = isset($rf->efectivo_administrador) && $rf->efectivo_administrador !== null
            ? (float) $rf->efectivo_administrador
            : null;

        $digitalAdmin = isset($rf->digital_administrador) && $rf->digital_administrador !== null
            ? (float) $rf->digital_administrador
            : null;

        $efectivoOperadorEfectivo = ($efectivoOperador == 0.0 && $efectivoAdmin !== null)
            ? $efectivoAdmin
            : $efectivoOperador;

        $digitalOperadorEfectivo = ($digitalOperador == 0.0 && $digitalAdmin !== null)
            ? $digitalAdmin
            : $digitalOperador;

        $montoOperador = round(
            $efectivoOperadorEfectivo + $digitalOperadorEfectivo,
            2
        );

        $efectivoAdminEfectivo = $efectivoAdmin !== null
            ? $efectivoAdmin
            : $efectivoOperador;

        $digitalAdminEfectivo = $digitalAdmin !== null
            ? $digitalAdmin
            : $digitalOperador;

        $montoAdministrador = round(
            $efectivoAdminEfectivo + $digitalAdminEfectivo,
            2
        );

        $turnoValidado = !empty($rf->validado_efectivo_en)
            && in_array(
                $rf->estado_validacion_digital ?? null,
                ['VALIDADO', 'OBSERVADO'],
                true
            );

        $montoConciliado = $turnoValidado
            ? $montoAdministrador
            : $montoOperador;

        return [
            'monto_a_entregar' => $montoAEntregar,
            'monto_operador' => $montoOperador,
            'monto_administrador' => $montoAdministrador,
            'monto_conciliado' => $montoConciliado,
            'turno_validado' => $turnoValidado,
            'diferencia' => round($montoConciliado - $montoAEntregar, 2),
        ];
    }
}
