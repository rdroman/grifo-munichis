<?php

namespace App\Services;

use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstruye el saldo contable de los controles futuros usando siempre el
 * efectivo ESPERADO anterior. No recalcula ventas ni altera arqueos físicos.
 *
 * Este servicio se ejecuta dentro de la transacción del controlador.
 */
class ServicioSaldoEsperadoCaja
{
    public function recalcularPosteriores(int $establecimientoId, string $fecha, ?Request $request = null): int
    {
        $tabla = 'controles_diarios_caja';
        $fechaCol = Esquema::resolver($tabla, ['fecha_control', 'fecha']);
        if (!$fechaCol) {
            throw new \RuntimeException('No se encontró la fecha del control de Caja.');
        }

        $columnas = array_flip(Schema::getColumnListing($tabla));
        foreach (['efectivo_dia_anterior', 'efectivo_esperado', 'efectivo_total_dia',
                  'gastos_efectivo_dia', 'compras_efectivo_dia', 'depositos_banco',
                  'otros_ingresos_caja', 'efectivo_contado', 'diferencia'] as $obligatoria) {
            if (!isset($columnas[$obligatoria])) {
                throw new \RuntimeException('La tabla Caja no tiene la columna ' . $obligatoria . '.');
            }
        }

        // Se considera el control de la fecha editada (si existe), o el último
        // anterior a una fecha eliminada. Así se evitan entradas de arqueo físico.
        $controlBase = DB::table($tabla)
            ->where('establecimiento_id', $establecimientoId)
            ->whereDate($fechaCol, '<=', $fecha)
            ->orderByDesc($fechaCol)->orderByDesc('id')
            ->lockForUpdate()->first();
        $saldo = $this->esperadoDelControl($controlBase);

        $posteriores = DB::table($tabla)
            ->where('establecimiento_id', $establecimientoId)
            ->whereDate($fechaCol, '>', $fecha)
            ->orderBy($fechaCol)->orderBy('id')
            ->lockForUpdate()->get();

        $recalculados = 0;
        foreach ($posteriores as $registro) {
            $esperado = round(
                $saldo
                + (float) ($registro->efectivo_total_dia ?? 0)
                + (float) ($registro->otros_ingresos_caja ?? 0)
                - (float) ($registro->gastos_efectivo_dia ?? 0)
                - (float) ($registro->compras_efectivo_dia ?? 0)
                - (float) ($registro->depositos_banco ?? 0), 2
            );
            $contado = $registro->efectivo_contado;
            $diferencia = $contado === null ? null : round((float) $contado - $esperado, 2);

            $nuevos = [
                'efectivo_dia_anterior' => $saldo,
                'efectivo_esperado' => $esperado,
                'efectivo_libro' => $esperado,
                'efectivo_sistema' => $esperado,
                'diferencia' => $diferencia,
            ];
            $cambios = [];
            foreach ($nuevos as $campo => $valor) {
                if (!isset($columnas[$campo])) {
                    continue;
                }
                $anterior = data_get($registro, $campo);
                if ($valor === null ? $anterior !== null
                    : ($anterior === null || round((float) $anterior, 2) !== round((float) $valor, 2))) {
                    $cambios[$campo] = $valor;
                }
            }

            if ($cambios !== []) {
                DB::table($tabla)->where('id', $registro->id)->update($cambios);
                if ($request !== null) {
                    ServicioAuditoria::registrar(
                        'CONTROL_CAJA', (int) $registro->id,
                        'RECALCULAR_SALDO', (array) $registro, $cambios, $request
                    );
                }
                $recalculados++;
            }
            $saldo = $esperado;
        }
        return $recalculados;
    }

    private function esperadoDelControl(?object $registro): float
    {
        if ($registro === null) {
            return 0.0; // Primera fecha registrada en Caja.
        }
        foreach (['efectivo_esperado', 'efectivo_libro', 'efectivo_sistema'] as $campo) {
            $valor = data_get($registro, $campo);
            if ($valor !== null) {
                return round((float) $valor, 2);
            }
        }
        throw new \RuntimeException('El control de Caja ID ' . $registro->id . ' carece de efectivo esperado.');
    }
}
