<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ControladorPanelSupervisor extends ControladorBaseGrifo
{
    public function indice()
    {
        $this->autorizar(['SUPERVISOR']);

        $hoy = now('America/Lima')->toDateString();
        $fechaReferencia = $this->ultimaFechaOperativa($hoy);

        $ventasDia = 0.0;
        if ($fechaReferencia && Schema::hasTable('resumen_financiero_turno')) {
            $ventasDia = (float) DB::table('resumen_financiero_turno as rf')
                ->join('turnos_operativos as t', 't.id', '=', 'rf.turno_operativo_id')
                ->where('t.establecimiento_id', $this->establecimientoId())
                ->whereDate('t.fecha_operacion', $fechaReferencia)
                ->selectRaw('SUM(COALESCE(rf.ventas_brutas_combustible,0)+COALESCE(rf.otros_ingresos,0)+COALESCE(rf.ventas_lubricantes,0)) as total')
                ->value('total') ?? 0.0;
        }

        $galonesPorProducto = collect();
        if ($fechaReferencia && Schema::hasTable('totales_producto_turno')) {
            $galonesPorProducto = DB::table('totales_producto_turno as tp')
                ->join('turnos_operativos as t', 't.id', '=', 'tp.turno_operativo_id')
                ->join('productos as p', 'p.id', '=', 'tp.producto_id')
                ->where('t.establecimiento_id', $this->establecimientoId())
                ->whereDate('t.fecha_operacion', $fechaReferencia)
                ->where('p.categoria', 'COMBUSTIBLE')
                ->groupBy('p.id', 'p.codigo', 'p.nombre')
                ->orderBy('p.id')
                ->get([
                    'p.codigo',
                    'p.nombre',
                    DB::raw('SUM(COALESCE(tp.galones,0)) as galones'),
                ]);
        }

        $galonesDia = (float) $galonesPorProducto->sum('galones');

        $ultimosTurnos = DB::table('turnos_operativos as t')
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->leftJoin('trabajadores as tr', 'tr.id', '=', 't.trabajador_id')
            ->leftJoin('resumen_financiero_turno as rf', 'rf.turno_operativo_id', '=', 't.id')
            ->where('t.establecimiento_id', $this->establecimientoId())
            ->orderByDesc('t.fecha_operacion')
            ->orderByDesc('t.id')
            ->limit(6)
            ->get([
                't.id as turno_operativo_id',
                't.fecha_operacion',
                DB::raw("COALESCE(tt.codigo,'—') as codigo_turno"),
                DB::raw("COALESCE(tr.nombre_completo,'—') as trabajador"),
                DB::raw('COALESCE(rf.ventas_brutas_combustible,0)+COALESCE(rf.otros_ingresos,0)+COALESCE(rf.ventas_lubricantes,0) as ventas_brutas_combustible'),
            ]);

        $tanques = collect();
        if (Schema::hasTable('tanques')) {
            $tanques = DB::table('tanques as ta')
                ->join('productos as p', 'p.id', '=', 'ta.producto_id')
                ->where('ta.establecimiento_id', $this->establecimientoId())
                ->where('p.categoria', 'COMBUSTIBLE')
                ->select('ta.id', 'p.codigo', 'p.nombre')
                ->orderBy('p.id')
                ->get();

            if (Schema::hasTable('controles_diarios_tanque')) {
                $fechaCol = $this->columna('controles_diarios_tanque', ['fecha_control','fecha']);
                $saldoCol = $this->columna('controles_diarios_tanque', ['saldo_medido_galones','saldo_medido_gal','saldo_medido']);
                if ($fechaCol && $saldoCol) {
                    foreach ($tanques as $tanque) {
                        $ultimo = DB::table('controles_diarios_tanque')
                            ->where('tanque_id', $tanque->id)
                            ->orderByDesc($fechaCol)
                            ->orderByDesc('id')
                            ->first();
                        $tanque->saldo_medido = $ultimo ? (float) data_get($ultimo, $saldoCol, 0) : null;
                        $tanque->fecha_control = $ultimo ? data_get($ultimo, $fechaCol) : null;
                    }
                }
            }
        }

        return view('panel_supervisor', compact(
            'fechaReferencia',
            'ventasDia',
            'galonesDia',
            'galonesPorProducto',
            'ultimosTurnos',
            'tanques'
        ));
    }

    private function ultimaFechaOperativa(string $hoy): ?string
    {
        if (! Schema::hasTable('turnos_operativos')) {
            return null;
        }

        $q = DB::table('turnos_operativos')
            ->where('establecimiento_id', $this->establecimientoId());

        if ((clone $q)->whereDate('fecha_operacion', $hoy)->exists()) {
            return $hoy;
        }

        return (clone $q)->max('fecha_operacion');
    }

    private function columna(string $tabla, array $candidatas): ?string
    {
        if (! Schema::hasTable($tabla)) {
            return null;
        }
        $columnas = array_flip(Schema::getColumnListing($tabla));
        foreach ($candidatas as $candidata) {
            if (isset($columnas[$candidata])) {
                return $candidata;
            }
        }
        return null;
    }
}
