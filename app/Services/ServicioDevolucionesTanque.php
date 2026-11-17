<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Devoluciones físicas al tanque: siempre se suman al ingreso, nunca se
 * restan de los galones despachados por el totalizador.
 */
class ServicioDevolucionesTanque
{
    public function totalDevoluciones(int $establecimientoId, int $tanqueId, string $fecha): float
    {
        return round((float) DB::table('devoluciones_tanque')
            ->where('establecimiento_id', $establecimientoId)
            ->where('tanque_id', $tanqueId)
            ->whereDate('fecha_operacion', $fecha)
            ->sum('galones'), 4);
    }

    /** Registro transaccional con verificación del turno y conciliación histórica. */
    public function registrar(
        int $establecimientoId,
        int $tanqueId,
        int $turnoId,
        string $fecha,
        string $galones,
        string $motivo,
        string $origen = 'MANUAL',
        ?string $referencia = null,
        ?Request $request = null
    ): array {
        return DB::transaction(function () use (
            $establecimientoId, $tanqueId, $turnoId, $fecha,
            $galones, $motivo, $origen, $referencia, $request
        ) {
            $tanque = DB::table('tanques')
                ->where('id', $tanqueId)
                ->where('establecimiento_id', $establecimientoId)
                ->lockForUpdate()->first();

            $turno = DB::table('turnos_operativos')
                ->where('id', $turnoId)
                ->where('establecimiento_id', $establecimientoId)
                ->whereDate('fecha_operacion', $fecha)
                ->first();

            if (! $tanque || ! $turno) {
                throw ValidationException::withMessages([
                    'devolucion_tanque_id' => 'Tanque, fecha o turno no válido para el establecimiento.',
                ]);
            }

            if ((float) $galones > (float) $tanque->capacidad_gal) {
                throw ValidationException::withMessages([
                    'devolucion_galones' => 'La devolución supera la capacidad del tanque.',
                ]);
            }

            // La referencia garantiza que el caso inicial es idempotente.
            if ($referencia !== null && DB::table('devoluciones_tanque')
                ->where('referencia', $referencia)->exists()) {
                return ['creada' => false, 'controles' => 0,
                    'mensaje' => 'La devolución con esta referencia ya estaba registrada.'];
            }

            // Evita doble clic o reiterar accidentalmente exactamente el mismo asiento.
            $duplicada = DB::table('devoluciones_tanque')
                ->where('establecimiento_id', $establecimientoId)
                ->where('tanque_id', $tanqueId)
                ->where('turno_operativo_id', $turnoId)
                ->where('galones', number_format((float) $galones, 4, '.', ''))
                ->where('motivo', $motivo)
                ->exists();

            if ($duplicada) {
                return ['creada' => false, 'controles' => 0,
                    'mensaje' => 'Ya existe una devolución idéntica para este tanque y turno. No se duplicó.'];
            }

            $datos = [
                'establecimiento_id' => $establecimientoId,
                'tanque_id' => $tanqueId,
                'turno_operativo_id' => $turnoId,
                'fecha_operacion' => $fecha,
                'galones' => number_format((float) $galones, 4, '.', ''),
                'motivo' => $motivo,
                'origen' => $origen,
                'referencia' => $referencia,
                'creado_por_usuario_id' => auth()->id(),
                'creado_en' => now('America/Lima'),
            ];

            $id = DB::table('devoluciones_tanque')->insertGetId($datos);

            if ($request !== null) {
                ServicioAuditoria::registrar(
                    'DEVOLUCION_TANQUE', $id, 'CREAR', null,
                    array_merge(['id' => $id], $datos), $request
                );
            }

            $resultado = $this->sincronizarControles(
                $establecimientoId, $tanqueId, $fecha, $request
            );

            $mensaje = 'Devolución de '.number_format((float) $galones, 4, '.', '')
                .' galones registrada correctamente como ingreso del tanque.';

            if ($resultado['sin_control']) {
                $mensaje .= ' ATENCIÓN: No existe control diario para esa fecha. '
                    .'Registre la medición del día para actualizar la cadena de saldos.';
            } else {
                $mensaje .= ' Controles de inventario actualizados: '
                    .$resultado['modificados'].'.';
            }

            return ['creada' => true, 'controles' => $resultado['modificados'],
                'sin_control' => $resultado['sin_control'], 'mensaje' => $mensaje];
        }, 3);
    }

    /**
     * Recalcula el ingreso y la cadena teórica, preservando salidas y mediciones.
     * Si falta la fecha, no se crea una medición ficticia.
     * Si el día falta, un control posterior se regulariza al registrar ese día.
     */
    private function sincronizarControles(
        int $establecimientoId,
        int $tanqueId,
        string $fecha,
        ?Request $request
    ): array {
        $controlDelDia = DB::table('controles_diarios_tanque')
            ->where('tanque_id', $tanqueId)
            ->whereDate('fecha_control', $fecha)
            ->lockForUpdate()->first();

        if (! $controlDelDia) {
            // La devolución se guarda, pero sin inventar balances o medidas.
            return ['sin_control' => true, 'modificados' => 0];
        }

        $anterior = DB::table('controles_diarios_tanque')
            ->where('tanque_id', $tanqueId)
            ->whereDate('fecha_control', '<', $fecha)
            ->orderByDesc('fecha_control')->orderByDesc('id')
            ->lockForUpdate()->first();

        // Para el primer control histórico se conserva la base implícita previa.
        if (! $anterior && $controlDelDia->saldo_teorico_gal === null) {
            throw ValidationException::withMessages([
                'devolucion_fecha' => 'El primer control no tiene saldo teórico; debe regularizarse antes de registrar la devolución.',
            ]);
        }
        $saldoPrevio = $anterior
            ? (float) ($anterior->saldo_teorico_gal ?? $anterior->saldo_medido_gal ?? 0)
            : (float) $controlDelDia->saldo_teorico_gal
                - (float) $controlDelDia->ingreso_gal
                + (float) $controlDelDia->salida_total_gal;
        $saldoPrevio = round($saldoPrevio, 4);

        $controles = DB::table('controles_diarios_tanque')
            ->where('tanque_id', $tanqueId)
            ->whereDate('fecha_control', '>=', $fecha)
            ->orderBy('fecha_control')->orderBy('id')
            ->lockForUpdate()->get();

        $productoId = (int) DB::table('tanques')->where('id', $tanqueId)->value('producto_id');
        $modificados = 0;

        foreach ($controles as $control) {
            $dia = (string) $control->fecha_control;
            $compras = (float) DB::table('compras_combustible')
                ->where('establecimiento_id', $establecimientoId)
                ->where('producto_id', $productoId)
                ->whereDate('fecha_compra', $dia)
                ->sum('galones');
            $devoluciones = $this->totalDevoluciones($establecimientoId, $tanqueId, $dia);
            $ingreso = round($compras + $devoluciones, 4);
            $teorico = round($saldoPrevio + $ingreso - (float) $control->salida_total_gal, 4);
            $diferencia = $control->saldo_medido_gal === null
                ? null : round((float) $control->saldo_medido_gal - $teorico, 4);

            $sinCambios = round((float) $control->ingreso_gal, 4) === $ingreso
                && $control->saldo_teorico_gal !== null
                && round((float) $control->saldo_teorico_gal, 4) === $teorico
                && (($control->diferencia_gal === null && $diferencia === null)
                    || ($control->diferencia_gal !== null && $diferencia !== null
                        && round((float) $control->diferencia_gal, 4) === $diferencia));

            if (! $sinCambios) {
                $cambios = [
                    'ingreso_gal' => $ingreso,
                    'saldo_teorico_gal' => $teorico,
                    'diferencia_gal' => $diferencia,
                ];
                DB::table('controles_diarios_tanque')->where('id', $control->id)->update($cambios);

                if ($request !== null) {
                    ServicioAuditoria::registrar(
                        'CONTROL_TANQUE', $control->id, 'ACTUALIZAR',
                        (array) $control, array_merge((array) $control, $cambios), $request
                    );
                }
                $modificados++;
            }
            $saldoPrevio = $teorico;
        }

        return ['sin_control' => false, 'modificados' => $modificados];
    }
}
