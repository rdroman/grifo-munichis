<?php

namespace App\Http\Controllers;

use App\Services\ServicioAuditoria;
use App\Services\ServicioSaldoEsperadoCaja;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ControladorCaja extends ControladorBaseGrifo
{
    private ?array $idsMediosEfectivo = null;

    public function indice(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD','LECTURA']);

        $establecimientoId = $this->establecimientoId();
        $fechaCol = Esquema::resolver('controles_diarios_caja', ['fecha_control','fecha']);

        $desde = $request->query('desde', now('America/Lima')->subDays(45)->toDateString());
        $hasta = $request->query('hasta', now('America/Lima')->toDateString());

        $consulta = DB::table('controles_diarios_caja')
            ->where('establecimiento_id', $establecimientoId);

        if ($fechaCol && $desde) {
            $consulta->whereDate($fechaCol, '>=', $desde);
        }

        if ($fechaCol && $hasta) {
            $consulta->whereDate($fechaCol, '<=', $hasta);
        }

        $historial = $consulta
            ->orderByDesc($fechaCol ?: 'id')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $ultimo = DB::table('controles_diarios_caja')
            ->where('establecimiento_id', $establecimientoId)
            ->orderByDesc($fechaCol ?: 'id')
            ->orderByDesc('id')
            ->first();

        return view('caja.indice', compact('historial', 'ultimo', 'desde', 'hasta', 'fechaCol'));
    }

    public function crear(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR']);

        $fecha = $request->query('fecha', now('America/Lima')->toDateString());

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fecha)) {
            $fecha = now('America/Lima')->toDateString();
        }

        $registro = $this->registroDelDia($fecha);
        $resumen = $this->calcularDia($fecha);

        $resumenInicial = array_merge($resumen, [
            'depositos_banco' => (float) data_get($registro, 'depositos_banco', 0),
            'otros_ingresos_caja' => (float) data_get($registro, 'otros_ingresos_caja', 0),
            'efectivo_contado' => data_get($registro, 'efectivo_contado'),
            'observaciones' => (string) data_get($registro, 'observaciones', ''),
            'existe' => (bool) $registro,
        ]);

        return view('caja.formulario', compact('fecha', 'resumenInicial'));
    }

    public function resumen(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR']);

        $datos = $request->validate([
            'fecha' => ['required','date'],
        ]);

        $fecha = $datos['fecha'];
        $registro = $this->registroDelDia($fecha);
        $resumen = $this->calcularDia($fecha);

        return response()->json(array_merge($resumen, [
            'depositos_banco' => (float) data_get($registro, 'depositos_banco', 0),
            'otros_ingresos_caja' => (float) data_get($registro, 'otros_ingresos_caja', 0),
            'efectivo_contado' => data_get($registro, 'efectivo_contado'),
            'observaciones' => (string) data_get($registro, 'observaciones', ''),
            'existe' => (bool) $registro,
        ]));
    }

    public function guardar(Request $request, ServicioSaldoEsperadoCaja $saldos)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR']);

        $datosEntrada = $request->validate([
            'fecha_control' => ['required','date'],
            'depositos_banco' => ['nullable','numeric','min:0'],
            'otros_ingresos_caja' => ['nullable','numeric','decimal:0,2','min:0','max:999999999999.99'],
            'efectivo_contado' => ['required','numeric','min:0'],
            'observaciones' => ['nullable','string','max:3000'],
        ]);

        $fecha = $datosEntrada['fecha_control'];
        $depositos = round((float) ($datosEntrada['depositos_banco'] ?? 0), 2);
        $otrosIngresosCaja = round((float) ($datosEntrada['otros_ingresos_caja'] ?? 0), 2);
        $efectivoContado = round((float) $datosEntrada['efectivo_contado'], 2);

        $recalculados = DB::transaction(function () use ($fecha, $depositos, $otrosIngresosCaja, $efectivoContado, $datosEntrada, $request, $saldos) {
            DB::table('establecimientos')->where('id', $this->establecimientoId())->lockForUpdate()->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | Lógica del control diario de caja
            |--------------------------------------------------------------------------
            | Efectivo esperado en caja =
            |   efectivo ESPERADO del día anterior
            | + suma de efectivo validado por Administración en los turnos del día
            | + otros ingresos a caja (independientes de los turnos)
            | - gastos en efectivo del día NO asignados a turnos
            | - compras del día pagadas en efectivo
            | - depósitos al banco
            |
            | Diferencia = efectivo contado - efectivo esperado
            |--------------------------------------------------------------------------
            */
            $auto = $this->calcularDia($fecha);

            $efectivoEsperado = round(
                $auto['efectivo_dia_anterior']
                + $auto['efectivo_total_dia']
                + $otrosIngresosCaja
                - $auto['gastos_efectivo_dia']
                - $auto['compras_efectivo_dia']
                - $depositos,
                2
            );

            $diferencia = round($efectivoContado - $efectivoEsperado, 2);

            $datosGuardar = [
                'establecimiento_id' => $this->establecimientoId(),
                'fecha_control' => $fecha,

                // Nuevos campos del control de caja.
                'efectivo_dia_anterior' => $auto['efectivo_dia_anterior'],
                'efectivo_total_dia' => $auto['efectivo_total_dia'],
                'gastos_efectivo_dia' => $auto['gastos_efectivo_dia'],
                'compras_efectivo_dia' => $auto['compras_efectivo_dia'],
                'depositos_banco' => $depositos,
                'otros_ingresos_caja' => $otrosIngresosCaja,
                'efectivo_esperado' => $efectivoEsperado,

                // Campos ya existentes.
                'efectivo_contado' => $efectivoContado,
                'diferencia' => $diferencia,
                'digital_diario' => $auto['efectivo_digital_dia'],
                'observaciones' => $datosEntrada['observaciones'] ?? null,
                'hoja_origen' => 'SISTEMA',
            ];

            /*
             * Compatibilidad con la estructura histórica:
             * - ingreso       = efectivo total del día
             * - efectivo_libro = efectivo esperado
             *
             * Si esos campos existen, se mantienen actualizados para no romper
             * reportes o consultas anteriores.
             */
            if ($colIngreso = Esquema::resolver('controles_diarios_caja', ['ingreso'])) {
                $datosGuardar[$colIngreso] = $auto['efectivo_total_dia'];
            }

            if ($colLibro = Esquema::resolver('controles_diarios_caja', ['efectivo_libro','efectivo_sistema'])) {
                $datosGuardar[$colLibro] = $efectivoEsperado;
            }

            $datosGuardar = Esquema::filtrar('controles_diarios_caja', $datosGuardar);

            // Guardado, auditoría y propagación de saldos como una sola transacción.
            // Al corregir un día histórico, los controles posteriores deben usar
            // el efectivo ESPERADO del control previo, nunca el efectivo contado.

            $existente = $this->registroDelDia($fecha);

            if ($existente) {
                $antes = (array) $existente;
                DB::table('controles_diarios_caja')
                    ->where('id', $existente->id)
                    ->update($datosGuardar);
                $id = $existente->id;
                $accion = 'ACTUALIZAR';
            } else {
                $antes = null;
                $id = DB::table('controles_diarios_caja')->insertGetId($datosGuardar);
                $accion = 'CREAR';
            }

            ServicioAuditoria::registrar(
                'CONTROL_CAJA', $id, $accion, $antes, $datosGuardar, $request
            );

            return $saldos->recalcularPosteriores($this->establecimientoId(), $fecha, $request);
        }, 3);

        $mensaje = 'Control de caja guardado correctamente.';
        if ($recalculados > 0) {
            $mensaje .= ' Se actualizaron ' . $recalculados . ' controles posteriores dependientes.';
        }

        return redirect()->route('caja.indice')->with('exito', $mensaje);
    }

    private function registroDelDia(string $fecha): ?object
    {
        $fechaCol = Esquema::resolver('controles_diarios_caja', ['fecha_control','fecha']);

        if (!$fechaCol) {
            return null;
        }

        return DB::table('controles_diarios_caja')
            ->where('establecimiento_id', $this->establecimientoId())
            ->whereDate($fechaCol, $fecha)
            ->first();
    }

    private function calcularDia(string $fecha): array
    {
        $turnos = $this->metricasTurnos($fecha);
        $gastosNoAsignados = $this->totalGastosEfectivoNoAsignados($fecha);
        $comprasDia = $this->totalComprasEfectivo($fecha);

        /*
        |--------------------------------------------------------------------------
        | Logica diaria corregida
        |--------------------------------------------------------------------------
        | El efectivo que ingresa desde los turnos NO se reconstruye desde
        | neto_esperado ni restando el digital.
        |
        | Se toma directamente el efectivo que Administracion valido en cada
        | turno: resumen_financiero_turno.efectivo_administrador.
        |
        | Los gastos vinculados a un turno ya estan reflejados en el efectivo
        | que finalmente fue entregado/validado para ese turno, por lo que NO
        | deben descontarse otra vez en el control diario.
        |--------------------------------------------------------------------------
        */

        return [
            'efectivo_dia_anterior' => $this->efectivoAnterior($fecha),
            'efectivo_total_dia' => round($turnos['efectivo_validado'], 2),
            'efectivo_digital_dia' => round($turnos['digital'], 2),
            'gastos_efectivo_dia' => round($gastosNoAsignados, 2),
            'compras_efectivo_dia' => round($comprasDia, 2),
            'turnos_total' => $turnos['turnos_total'],
            'turnos_validados' => $turnos['turnos_validados'],
            'turnos_pendientes' => $turnos['turnos_pendientes'],
        ];
    }

    private function metricasTurnos(string $fecha): array
    {
        if (!Schema::hasTable('resumen_financiero_turno') || !Schema::hasTable('turnos_operativos')) {
            return [
                'efectivo_validado' => 0.0,
                'digital' => 0.0,
                'turnos_total' => 0,
                'turnos_validados' => 0,
                'turnos_pendientes' => 0,
            ];
        }

        $filas = DB::table('resumen_financiero_turno as r')
            ->join('turnos_operativos as t', 't.id', '=', 'r.turno_operativo_id')
            ->where('t.establecimiento_id', $this->establecimientoId())
            ->whereDate('t.fecha_operacion', $fecha)
            ->select('r.*')
            ->get();

        $efectivoValidado = 0.0;
        $digital = 0.0;
        $turnosValidados = 0;

        foreach ($filas as $fila) {
            if (data_get($fila, 'efectivo_administrador') !== null) {
                $efectivoValidado += (float) data_get($fila, 'efectivo_administrador');
                $turnosValidados++;
            }

            foreach (['digital_administrador','digital_operador','digital_entregado'] as $campo) {
                $valor = data_get($fila, $campo);
                if ($valor !== null) {
                    $digital += (float) $valor;
                    break;
                }
            }
        }

        $turnosTotal = $filas->count();

        return [
            'efectivo_validado' => round($efectivoValidado, 2),
            'digital' => round($digital, 2),
            'turnos_total' => $turnosTotal,
            'turnos_validados' => $turnosValidados,
            'turnos_pendientes' => max(0, $turnosTotal - $turnosValidados),
        ];
    }

    private function efectivoAnterior(string $fecha): float
    {
        $fechaCol = Esquema::resolver('controles_diarios_caja', ['fecha_control','fecha']);

        if (!$fechaCol) {
            return 0.0;
        }

        $anterior = DB::table('controles_diarios_caja')
            ->where('establecimiento_id', $this->establecimientoId())
            ->whereDate($fechaCol, '<', $fecha)
            ->orderByDesc($fechaCol)
            ->orderByDesc('id')
            ->first();

        if (!$anterior) {
            return 0.0;
        }

        /*
         * El saldo de apertura deriva del efectivo ESPERADO del control previo.
         * Un faltante/sobrante del arqueo no altera el saldo contable del día
         * siguiente. Los campos de libro son solo respaldo histórico.
         */
        foreach (['efectivo_esperado','efectivo_libro','efectivo_sistema'] as $campo) {
            $valor = data_get($anterior, $campo);

            if ($valor !== null) {
                return round((float) $valor, 2);
            }
        }

        // Evita computar un saldo ficticio de S/ 0.00 cuando hay un control
        // anterior que carece tanto de esperado como de libro.
        throw new \RuntimeException('El control anterior no tiene efectivo esperado disponible. Revisar registro de Caja ID ' . $anterior->id . '.');
    }

    public function sincronizarGastosEliminados(string $fecha, float $gastosAntes, ServicioSaldoEsperadoCaja $saldos, Request $request): void
    {
        $gastos = $this->totalGastosEfectivoNoAsignados($fecha);
        if ($gastos === $gastosAntes) {
            return;
        }

        $tabla = 'controles_diarios_caja';
        $fechaCol = Esquema::resolver($tabla, ['fecha_control', 'fecha']);
        $consulta = DB::table($tabla)
            ->where('establecimiento_id', $this->establecimientoId())
            ->where($fechaCol, $fecha);
        $control = $consulta->lockForUpdate()->first();

        // El ajuste requiere el desglose del egreso en el control existente.
        if ($control === null || $control->gastos_efectivo_dia === null || $control->efectivo_esperado === null
            || $gastos === (float) $control->gastos_efectivo_dia) {
            return;
        }

        $esperado = round((float) $control->efectivo_esperado + (float) $control->gastos_efectivo_dia - $gastos, 2);
        $datos = Esquema::filtrar($tabla, [
            'gastos_efectivo_dia' => $gastos,
            'efectivo_esperado' => $esperado,
            'efectivo_libro' => $esperado,
            'efectivo_sistema' => $esperado,
            'diferencia' => $control->efectivo_contado === null
                ? null
                : round((float) $control->efectivo_contado - $esperado, 2),
        ]);

        $consulta->where('id', $control->id)->update($datos);
        ServicioAuditoria::registrar('CONTROL_CAJA', (int) $control->id, 'ACTUALIZAR', (array) $control, $datos, $request);
        $saldos->recalcularPosteriores($this->establecimientoId(), $fecha, $request);
    }

    public function totalGastosEfectivoNoAsignados(string $fecha): float
    {
        if (!Schema::hasTable('gastos')) {
            return 0.0;
        }

        $fechaCol = Esquema::resolver('gastos', ['fecha_gasto','fecha','fecha_movimiento']);
        $montoCol = Esquema::resolver('gastos', ['monto','importe','costo_total','costo']);
        $medioCol = Esquema::resolver('gastos', ['medio_pago_id']);
        $detalleCol = Esquema::resolver('gastos', ['detalle','descripcion','concepto']);

        if (!$fechaCol || !$montoCol || !$medioCol) {
            return 0.0;
        }

        $idsEfectivo = $this->idsMedioPagoEfectivo();

        if (empty($idsEfectivo)) {
            return 0.0;
        }

        $consulta = DB::table('gastos')
            ->where('establecimiento_id', $this->establecimientoId())
            ->whereDate($fechaCol, $fecha)
            ->whereIn($medioCol, $idsEfectivo);

        /*
         * Un gasto asignado a turno ya redujo el efectivo entregado/validado
         * en ese turno. Descontarlo nuevamente duplicaria el egreso.
         */
        if (Schema::hasColumn('gastos', 'turno_operativo_id')) {
            $consulta->whereNull('turno_operativo_id');
        }

        /*
         * Los depósitos bancarios históricos aparecían como "gasto" en Excel.
         * En el nuevo control son una salida separada y NO deben duplicarse.
         */
        if ($detalleCol) {
            $consulta->where(function ($q) use ($detalleCol) {
                $q->whereNull($detalleCol)
                    ->orWhere(function ($qq) use ($detalleCol) {
                        $qq->where($detalleCol, 'not like', '%deposit%')
                           ->where($detalleCol, 'not like', '%depósito%');
                    });
            });
        }

        return round((float) $consulta->sum($montoCol), 2);
    }

    private function totalComprasEfectivo(string $fecha): float
    {
        if (!Schema::hasTable('compras_combustible')) {
            return 0.0;
        }

        $fechaCol = Esquema::resolver('compras_combustible', ['fecha_compra','fecha']);
        $montoCol = Esquema::resolver('compras_combustible', ['costo_total','monto_total','importe_total']);
        $medioCol = Esquema::resolver('compras_combustible', ['medio_pago_id']);

        if (!$fechaCol || !$montoCol || !$medioCol) {
            return 0.0;
        }

        $idsEfectivo = $this->idsMedioPagoEfectivo();

        if (empty($idsEfectivo)) {
            return 0.0;
        }

        return round((float) DB::table('compras_combustible')
            ->where('establecimiento_id', $this->establecimientoId())
            ->whereDate($fechaCol, $fecha)
            ->whereIn($medioCol, $idsEfectivo)
            ->sum($montoCol), 2);
    }

    private function idsMedioPagoEfectivo(): array
    {
        if ($this->idsMediosEfectivo !== null) {
            return $this->idsMediosEfectivo;
        }

        if (!Schema::hasTable('medios_pago')) {
            return $this->idsMediosEfectivo = [];
        }

        $filas = DB::table('medios_pago')->get();
        $ids = [];

        foreach ($filas as $fila) {
            $textos = [
                data_get($fila, 'codigo'),
                data_get($fila, 'nombre'),
                data_get($fila, 'tipo'),
                data_get($fila, 'clase'),
                data_get($fila, 'naturaleza'),
            ];

            foreach ($textos as $texto) {
                if ($texto === null) {
                    continue;
                }

                $normalizado = Str::upper(Str::ascii((string) $texto));

                if ($normalizado === 'CASH' || str_contains($normalizado, 'EFECTIVO')) {
                    $ids[] = (int) data_get($fila, 'id');
                    break;
                }
            }
        }

        return $this->idsMediosEfectivo = array_values(array_unique($ids));
    }
}
