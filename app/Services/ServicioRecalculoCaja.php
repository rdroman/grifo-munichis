<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServicioRecalculoCaja
{
    /**
     * Recalcula la cadena de controles de caja desde una fecha determinada.
     *
     * La fórmula replica exactamente la utilizada por ControladorCaja:
     * esperado = anterior + efectivo_total_dia - gastos_efectivo_dia
     *            - compras_efectivo_dia - depositos_banco
     * diferencia = efectivo_contado - efectivo_esperado
     *
     * El efectivo contado es un dato físico/manual y nunca se modifica.
     */
    public function recalcularDesde(int $establecimientoId, string $fechaDesde): int
    {
        if (! Schema::hasTable('controles_diarios_caja')) {
            return 0;
        }

        $columnas = array_flip(Schema::getColumnListing('controles_diarios_caja'));
        $requeridas = [
            'id',
            'establecimiento_id',
            'fecha_control',
            'efectivo_dia_anterior',
            'efectivo_total_dia',
            'gastos_efectivo_dia',
            'compras_efectivo_dia',
            'depositos_banco',
            'efectivo_esperado',
            'efectivo_contado',
            'diferencia',
        ];

        foreach ($requeridas as $columna) {
            if (! isset($columnas[$columna])) {
                return 0;
            }
        }

        $previo = DB::table('controles_diarios_caja')
            ->where('establecimiento_id', $establecimientoId)
            ->where('fecha_control', '<', $fechaDesde)
            ->orderByDesc('fecha_control')
            ->orderByDesc('id')
            ->first();

        $saldoAnterior = $this->saldoCierre($previo);

        $filas = DB::table('controles_diarios_caja')
            ->where('establecimiento_id', $establecimientoId)
            ->where('fecha_control', '>=', $fechaDesde)
            ->orderBy('fecha_control')
            ->orderBy('id')
            ->get();

        $actualizados = 0;

        foreach ($filas as $fila) {
            $efectivoTotal = (float) data_get($fila, 'efectivo_total_dia', 0);
            $gastos = (float) data_get($fila, 'gastos_efectivo_dia', 0);
            $compras = (float) data_get($fila, 'compras_efectivo_dia', 0);
            $depositos = (float) data_get($fila, 'depositos_banco', 0);

            $esperado = round(
                $saldoAnterior
                + $efectivoTotal
                - $gastos
                - $compras
                - $depositos,
                2
            );

            $cambios = [
                'efectivo_dia_anterior' => round($saldoAnterior, 2),
                'efectivo_esperado' => $esperado,
            ];

            if (data_get($fila, 'efectivo_contado') !== null) {
                $cambios['diferencia'] = round(
                    (float) data_get($fila, 'efectivo_contado') - $esperado,
                    2
                );
            } else {
                $cambios['diferencia'] = null;
            }

            DB::table('controles_diarios_caja')
                ->where('id', $fila->id)
                ->update($cambios);

            $actualizados++;

            // El siguiente día parte del cierre físico contado cuando existe;
            // de lo contrario, del esperado recalculado.
            $saldoAnterior = data_get($fila, 'efectivo_contado') !== null
                ? (float) data_get($fila, 'efectivo_contado')
                : $esperado;
        }

        return $actualizados;
    }

    private function saldoCierre(?object $fila): float
    {
        if (! $fila) {
            return 0.0;
        }

        if (data_get($fila, 'efectivo_contado') !== null) {
            return (float) data_get($fila, 'efectivo_contado');
        }

        return (float) data_get($fila, 'efectivo_esperado', 0);
    }
}
