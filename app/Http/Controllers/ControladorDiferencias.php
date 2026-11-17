<?php

namespace App\Http\Controllers;

use App\Services\ServicioFinancieroTurno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControladorDiferencias extends ControladorBaseGrifo
{
    public function indice(Request $request)
    {
        $this->autorizar([
            'ADMINISTRADOR',
            'GERENCIA',
            'SUPERVISOR',
            'CONTABILIDAD',
            'LECTURA',
        ]);

        $desde = $request->query(
            'desde',
            now('America/Lima')->subDays(30)->toDateString()
        );

        $hasta = $request->query(
            'hasta',
            now('America/Lima')->toDateString()
        );

        /*
        |--------------------------------------------------------------------------
        | Conciliación administrativa del turno
        |--------------------------------------------------------------------------
        |
        | La fuente financiera del importe esperado es:
        |
        |   ServicioFinancieroTurno::calcular()
        |
        | De esta forma Turnos y Sobrantes/Faltantes utilizan exactamente
        | la misma lógica de negocio.
        |
        | Diferencia:
        |
        |   monto conciliado - monto_a_entregar
        |
        |--------------------------------------------------------------------------
        */

        $consulta = DB::table('turnos_operativos as t')
            ->join(
                'resumen_financiero_turno as rf',
                'rf.turno_operativo_id',
                '=',
                't.id'
            )
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->leftJoin('trabajadores as tr', 'tr.id', '=', 't.trabajador_id')
            ->where('t.establecimiento_id', $this->establecimientoId())
            ->whereDate('t.fecha_operacion', '>=', $desde)
            ->whereDate('t.fecha_operacion', '<=', $hasta)
            ->select([
                't.id as turno_operativo_id',
                't.fecha_operacion',
                'tt.codigo as turno',
                'tr.nombre_completo as trabajador',

                /*
                 * Se conserva únicamente como fallback defensivo.
                 * El valor normal se recalcula con ServicioFinancieroTurno.
                 */
                'rf.neto_esperado',

                'rf.efectivo_entregado as efectivo_operador',
                'rf.digital_operador',

                'rf.efectivo_administrador',
                'rf.digital_administrador',

                'rf.validado_efectivo_en',
                'rf.estado_validacion_digital',
            ])
            ->orderByDesc('t.fecha_operacion')
            ->orderByDesc('t.id');

        $registros = $consulta
            ->paginate(50)
            ->withQueryString();

        $servicioFinanciero = app(ServicioFinancieroTurno::class);

        $registros->getCollection()->transform(
            function ($fila) use ($servicioFinanciero) {

                /*
                 * -------------------------------------------------------------
                 * 1. Recalcular el monto esperado con la misma fuente de Turnos
                 * -------------------------------------------------------------
                 */
                try {
                    $calculo = $servicioFinanciero->calcular(
                        (int) $fila->turno_operativo_id
                    );

                    $netoEsperado = round(
                        (float) ($calculo['monto_a_entregar'] ?? 0),
                        2
                    );
                } catch (\Throwable $e) {
                    /*
                     * Fallback para registros históricos anómalos.
                     */
                    $netoEsperado = round(
                        (float) ($fila->neto_esperado ?? 0),
                        2
                    );

                    $fila->calculo_financiero_error = $e->getMessage();
                }

                $fila->neto_esperado = $netoEsperado;

                /*
                 * -------------------------------------------------------------
                 * 2. Reproducir exactamente la conciliación usada por Turnos
                 * -------------------------------------------------------------
                 */

                $efectivoOperadorOriginal =
                    $fila->efectivo_operador ?? null;

                $digitalOperadorOriginal =
                    $fila->digital_operador ?? null;

                $efectivoAdmin =
                    $fila->efectivo_administrador ?? null;

                $digitalAdmin =
                    $fila->digital_administrador ?? null;

                /*
                 * Compatibilidad con datos históricos:
                 * si el operador tiene NULL/0 y existe dato administrativo,
                 * usamos el administrativo como fallback.
                 */
                $efectivoOperador = (
                    (
                        $efectivoOperadorOriginal === null
                        || (float) $efectivoOperadorOriginal == 0.0
                    )
                    && $efectivoAdmin !== null
                )
                    ? (float) $efectivoAdmin
                    : (float) ($efectivoOperadorOriginal ?? 0);

                $digitalOperador = (
                    (
                        $digitalOperadorOriginal === null
                        || (float) $digitalOperadorOriginal == 0.0
                    )
                    && $digitalAdmin !== null
                )
                    ? (float) $digitalAdmin
                    : (float) ($digitalOperadorOriginal ?? 0);

                $montoOperador = round(
                    $efectivoOperador + $digitalOperador,
                    2
                );

                $hayValidacionAdministrativa =
                    $efectivoAdmin !== null
                    || $digitalAdmin !== null;

                $montoAdmin = $hayValidacionAdministrativa
                    ? round(
                        (float) ($efectivoAdmin ?? $efectivoOperador)
                        + (float) ($digitalAdmin ?? $digitalOperador),
                        2
                    )
                    : null;

                /*
                 * Mismo criterio utilizado por ControladorTurnos.
                 */
                $turnoValidado =
                    ($fila->validado_efectivo_en ?? null) !== null
                    && ($fila->estado_validacion_digital ?? null)
                        === 'VALIDADO';

                $montoBase = (
                    $turnoValidado
                    && $montoAdmin !== null
                )
                    ? $montoAdmin
                    : $montoOperador;

                /*
                 * -------------------------------------------------------------
                 * 3. Valores de salida para la vista
                 * -------------------------------------------------------------
                 */

                $fila->dinero_operador = $montoOperador;

                $fila->dinero_validado = $turnoValidado
                    ? $montoBase
                    : null;

                /*
                 * En Sobrantes/Faltantes sólo mostramos diferencia definitiva
                 * cuando el turno está totalmente validado.
                 */
                $fila->diferencia = $turnoValidado
                    ? round(
                        $montoBase - $netoEsperado,
                        2
                    )
                    : null;

                $fila->estado = $turnoValidado
                    ? 'VALIDADO'
                    : 'PENDIENTE';

                return $fila;
            }
        );

        $vista = view()->exists('diferencias.indice')
            ? 'diferencias.indice'
            : 'diferencias';

        return view($vista, compact(
            'registros',
            'desde',
            'hasta'
        ));
    }
}