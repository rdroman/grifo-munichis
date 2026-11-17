<?php

namespace App\Http\Controllers;

use App\Services\ServicioAuditoria;
use App\Services\ServicioDevolucionesTanque;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControladorTanques extends ControladorBaseGrifo
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

        $fechaCol = Esquema::resolver(
            'controles_diarios_tanque',
            ['fecha_control', 'fecha']
        );

        $tanques = DB::table('tanques as t')
            ->leftJoin('productos as p', 'p.id', '=', 't.producto_id')
            ->where('t.establecimiento_id', $this->establecimientoId())
            ->select(
                't.*',
                'p.codigo as producto_codigo',
                'p.nombre as producto'
            )
            ->orderBy('t.id')
            ->get();

        $tiposTurno = DB::table('tipos_turno')->orderBy('id')->get(['id', 'codigo', 'nombre']);

        $ultimos = collect();

        if ($fechaCol) {
            foreach ($tanques as $tanque) {
                $r = DB::table('controles_diarios_tanque')
                    ->where('tanque_id', $tanque->id)
                    ->orderByDesc($fechaCol)
                    ->orderByDesc('id')
                    ->first();

                if ($r) {
                    $ultimos->put($tanque->id, $r);
                }
            }
        }

        $desde = $request->query(
            'desde',
            now()->subDays(30)->toDateString()
        );

        $hasta = $request->query(
            'hasta',
            now()->toDateString()
        );

        $historial = DB::table('controles_diarios_tanque as c')
            ->join('tanques as t', 't.id', '=', 'c.tanque_id')
            ->leftJoin('productos as p', 'p.id', '=', 't.producto_id')
            ->where('t.establecimiento_id', $this->establecimientoId())
            ->select(
                'c.*',
                't.nombre as tanque',
                'p.codigo as producto_codigo',
                'p.nombre as producto'
            );

        if ($fechaCol && $desde) {
            $historial->whereDate("c.$fechaCol", '>=', $desde);
        }

        if ($fechaCol && $hasta) {
            $historial->whereDate("c.$fechaCol", '<=', $hasta);
        }

        $historial = $historial
            ->orderByDesc($fechaCol ? "c.$fechaCol" : 'c.id')
            ->orderBy('t.id')
            ->paginate(30)
            ->withQueryString();

        $columnas = [
            'fecha' => $fechaCol,

            'ingreso' => Esquema::resolver(
                'controles_diarios_tanque',
                [
                    'galones_ingreso',
                    'galones_entrada',
                    'entrada_galones',
                    'ingreso_galones',
                    'ingreso_gal',
                ]
            ),

            'venta' => Esquema::resolver(
                'controles_diarios_tanque',
                [
                    'galones_venta_normal',
                    'venta_normal_galones',
                    'ventas_normales_gal',
                    'venta_normal_gal',
                ]
            ),

            'descuento' => Esquema::resolver(
                'controles_diarios_tanque',
                [
                    'galones_descuento',
                    'descuento_galones',
                    'descuento_gal',
                ]
            ),

            'credito' => Esquema::resolver(
                'controles_diarios_tanque',
                [
                    'galones_credito',
                    'credito_galones',
                    'credito_gal',
                ]
            ),

            'salida' => Esquema::resolver(
                'controles_diarios_tanque',
                [
                    'galones_salida_total',
                    'salida_total_galones',
                    'salida_total_gal',
                ]
            ),

            'teorico' => Esquema::resolver(
                'controles_diarios_tanque',
                [
                    'saldo_teorico_galones',
                    'saldo_teorico_gal',
                    'saldo_teorico',
                ]
            ),

            'medido' => Esquema::resolver(
                'controles_diarios_tanque',
                [
                    'saldo_medido_galones',
                    'saldo_medido_gal',
                    'saldo_medido',
                ]
            ),

            'diferencia' => Esquema::resolver(
                'controles_diarios_tanque',
                [
                    'diferencia_galones',
                    'diferencia_gal',
                    'diferencia',
                ]
            ),
        ];

        return view(
            'tanques.indice',
            compact(
                'tanques',
                'ultimos',
                'historial',
                'columnas',
                'desde',
                'hasta',
                'tiposTurno'
            )
        );
    }

    /**
     * Devuelve los valores automáticos para tanque + fecha.
     * Se usa desde JavaScript únicamente como vista previa.
     *
     * Al guardar, todos estos importes se vuelven a calcular
     * en el servidor.
     */
    public function calcular(Request $request)
    {
        $this->autorizar([
            'ADMINISTRADOR',
            'GERENCIA',
            'SUPERVISOR',
        ]);

        $datos = $request->validate([
            'tanque_id' => [
                'required',
                'integer',
                'exists:tanques,id',
            ],
            'fecha_control' => [
                'required',
                'date',
            ],
        ]);

        $calculo = $this->calcularValores(
            (int) $datos['tanque_id'],
            $datos['fecha_control']
        );

        // El tanque ya fue validado para el establecimiento en calcularValores.
        // Exponer la medicion previa en esta misma consulta evita una ruta extra.
        $existente = DB::table('controles_diarios_tanque')
            ->where('tanque_id', (int) $datos['tanque_id'])
            ->where('fecha_control', $datos['fecha_control'])
            ->first(['id', 'saldo_medido_gal']);

        return response()->json(array_merge($calculo, [
            'control_existente' => $existente !== null,
            'control_id' => $existente?->id,
            'saldo_medido_galones' => $existente?->saldo_medido_gal,
        ]));
    }

    /**
     * Calcula las existencias y movimientos de un tanque
     * para una fecha determinada.
     */
    private function calcularValores(
        int $tanqueId,
        string $fecha
    ): array {
        $establecimientoId = $this->establecimientoId();

        $tanque = DB::table('tanques')
            ->where('id', $tanqueId)
            ->where(
                'establecimiento_id',
                $establecimientoId
            )
            ->first();

        abort_unless($tanque, 404);

        $productoId = (int) $tanque->producto_id;

        /*
        |--------------------------------------------------------------------------
        | INGRESOS: compras + devoluciones registradas para ESTE tanque y fecha
        |--------------------------------------------------------------------------
        | Los totalizadores ya contienen el despacho; una devolucion fisica
        | no debe restarse otra vez de la salida para evitar duplicidades.
        */

        $compras = (float) DB::table('compras_combustible')
            ->where(
                'establecimiento_id',
                $establecimientoId
            )
            ->where('producto_id', $productoId)
            ->whereDate('fecha_compra', $fecha)
            ->sum('galones');

        $devoluciones = app(ServicioDevolucionesTanque::class)->totalDevoluciones(
            $establecimientoId, $tanqueId, $fecha
        );

        $ingreso = $compras + $devoluciones;

        /*
        |--------------------------------------------------------------------------
        | DESCUENTOS
        |--------------------------------------------------------------------------
        */

        $descuentos = (float) DB::table('descuentos')
            ->where(
                'establecimiento_id',
                $establecimientoId
            )
            ->where('producto_id', $productoId)
            ->whereDate('fecha_descuento', $fecha)
            ->sum('galones');

        /*
        |--------------------------------------------------------------------------
        | CRÉDITOS
        |--------------------------------------------------------------------------
        */

        $creditos = (float) DB::table('ventas_credito as v')
            ->join(
                'detalle_ventas_credito as d',
                'd.venta_credito_id',
                '=',
                'v.id'
            )
            ->where(
                'v.establecimiento_id',
                $establecimientoId
            )
            ->where('d.producto_id', $productoId)
            ->whereDate('v.fecha_venta', $fecha)
            ->sum('d.cantidad_gal');

        /*
        |--------------------------------------------------------------------------
        | TOTAL DESPACHADO SEGÚN TOTALIZADORES
        |--------------------------------------------------------------------------
        */

        $despachoTotal = (float) DB::table(
            'totales_producto_turno as tp'
        )
            ->join(
                'turnos_operativos as t',
                't.id',
                '=',
                'tp.turno_operativo_id'
            )
            ->where(
                't.establecimiento_id',
                $establecimientoId
            )
            ->where('tp.producto_id', $productoId)
            ->whereDate('t.fecha_operacion', $fecha)
            ->sum('tp.galones');

        /*
        |--------------------------------------------------------------------------
        | VENTA NORMAL
        |--------------------------------------------------------------------------
        |
        | El totalizador contiene todo el combustible despachado.
        | Por tanto, crédito y descuento se separan del despacho total.
        |
        */

        $ventaNormal = round(
            max(
                0,
                $despachoTotal
                - $descuentos
                - $creditos
            ),
            3
        );

        /*
        |--------------------------------------------------------------------------
        | SALDO ANTERIOR
        |--------------------------------------------------------------------------
        |
        | Se usa el saldo teórico del último control anterior.
        | No usamos automáticamente el saldo medido porque eso absorbería
        | faltantes o sobrantes dentro del inventario teórico.
        |
        */

        $anterior = DB::table('controles_diarios_tanque')
            ->where('tanque_id', $tanqueId)
            ->whereDate('fecha_control', '<', $fecha)
            ->orderByDesc('fecha_control')
            ->orderByDesc('id')
            ->first();

        $saldoAnterior = 0.0;

        if ($anterior) {
            if ($anterior->saldo_teorico_gal !== null) {
                $saldoAnterior =
                    (float) $anterior->saldo_teorico_gal;
            } elseif ($anterior->saldo_medido_gal !== null) {
                /*
                 * Fallback únicamente para registros históricos
                 * que no tengan saldo teórico.
                 */
                $saldoAnterior =
                    (float) $anterior->saldo_medido_gal;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SALDO TEÓRICO
        |--------------------------------------------------------------------------
        */

        $salidaTotal = round(
            $ventaNormal + $descuentos + $creditos,
            3
        );

        $saldoTeorico = round(
            $saldoAnterior
            + $ingreso
            - $ventaNormal
            - $descuentos
            - $creditos,
            4
        );

        return [
            'tanque_id' => $tanqueId,
            'producto_id' => $productoId,
            'fecha_control' => $fecha,

            'saldo_anterior' => round(
                $saldoAnterior,
                4
            ),

            'compras' => round(
                $compras,
                3
            ),

            'devoluciones' => round(
                $devoluciones,
                4
            ),

            'ingreso' => round(
                $ingreso,
                4
            ),

            'despacho_total' => round(
                $despachoTotal,
                3
            ),

            'venta_normal' => round(
                $ventaNormal,
                3
            ),

            'descuentos' => round(
                $descuentos,
                3
            ),

            'creditos' => round(
                $creditos,
                3
            ),

            'salida_total' => round(
                $salidaTotal,
                3
            ),

            'saldo_teorico' => round(
                $saldoTeorico,
                4
            ),
        ];
    }

    /** Registrar una devolucion fisica. No modifica la salida de totalizadores. */
    public function guardarDevolucion(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR']);

        $datos = $request->validate([
            'devolucion_tanque_id' => ['required', 'integer', 'exists:tanques,id'],
            'devolucion_fecha' => ['required', 'date_format:Y-m-d'],
            'devolucion_tipo_turno_id' => ['required', 'integer', 'exists:tipos_turno,id'],
            'devolucion_galones' => [
                'required', 'numeric', 'gt:0', 'max:999999999.9999',
                'regex:/^\d{1,9}(?:\.\d{1,4})?$/',
            ],
            'devolucion_motivo' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $turnos = DB::table('turnos_operativos')
            ->where('establecimiento_id', $this->establecimientoId())
            ->whereDate('fecha_operacion', $datos['devolucion_fecha'])
            ->where('tipo_turno_id', (int) $datos['devolucion_tipo_turno_id'])
            ->get(['id']);

        if ($turnos->count() !== 1) {
            return back()->withErrors([
                'devolucion_tipo_turno_id' =>
                    'Debe existir exactamente un turno de este tipo en la fecha seleccionada. '.
                    'No se registró la devolución.'
            ])->withInput();
        }

        $resultado = app(ServicioDevolucionesTanque::class)->registrar(
            $this->establecimientoId(),
            (int) $datos['devolucion_tanque_id'],
            (int) $turnos->first()->id,
            (string) $datos['devolucion_fecha'],
            (string) $datos['devolucion_galones'],
            trim($datos['devolucion_motivo']),
            'MANUAL',
            null,
            $request
        );

        return redirect()->route('tanques.indice', [
            'desde' => $datos['devolucion_fecha'],
            'hasta' => now()->toDateString(),
        ])->with('exito', $resultado['mensaje']);
    }

    public function guardar(Request $request)
    {
        $this->autorizar([
            'ADMINISTRADOR',
            'GERENCIA',
            'SUPERVISOR',
        ]);

        // Los saldos de movimientos y teoricos se calculan en el servidor.
        $validados = $request->validate([
            'tanque_id' => ['required', 'integer', 'exists:tanques,id'],
            'fecha_control' => ['required', 'date_format:Y-m-d'],
            'saldo_medido_galones' => [
                'required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3',
            ],
        ]);

        // El bloqueo del tanque serializa guardados concurrentes del mismo
        // tanque, inclusive cuando aun no hay un control para esa fecha.
        [$accion, $posteriores] = DB::transaction(function () use ($validados, $request) {
            $tanque = DB::table('tanques')
                ->where('id', (int) $validados['tanque_id'])
                ->where('establecimiento_id', $this->establecimientoId())
                ->lockForUpdate()
                ->first();

            abort_unless($tanque, 404);

            $fecha = $validados['fecha_control'];
            $calculo = $this->calcularValores((int) $tanque->id, $fecha);
            $medido = round((float) $validados['saldo_medido_galones'], 3);

            $datos = Esquema::filtrar('controles_diarios_tanque', [
                'tanque_id' => (int) $tanque->id,
                'fecha_control' => $fecha,
                'ingreso_gal' => $calculo['ingreso'],
                'venta_normal_gal' => $calculo['venta_normal'],
                'descuento_gal' => $calculo['descuentos'],
                'credito_gal' => $calculo['creditos'],
                'salida_total_gal' => $calculo['salida_total'],
                'saldo_teorico_gal' => $calculo['saldo_teorico'],
                'saldo_medido_gal' => $medido,
                'diferencia_gal' => round($medido - $calculo['saldo_teorico'], 4),
                'hoja_origen' => 'SISTEMA',
            ]);

            $existente = DB::table('controles_diarios_tanque')
                ->where('tanque_id', $tanque->id)
                ->where('fecha_control', $fecha)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                // Conservar el origen de un registro historico importado.
                $datosActualizar = $datos;
                unset($datosActualizar['hoja_origen']);

                DB::table('controles_diarios_tanque')
                    ->where('id', $existente->id)
                    ->update($datosActualizar);

                $id = $existente->id;
                $accion = 'ACTUALIZAR';
                $antes = (array) $existente;
                $despues = array_merge($antes, $datosActualizar);
            } else {
                $id = DB::table('controles_diarios_tanque')->insertGetId($datos);
                $accion = 'CREAR';
                $antes = null;
                $despues = array_merge(['id' => $id], $datos);
            }

            ServicioAuditoria::registrar(
                'CONTROL_TANQUE', $id, $accion, $antes, $despues, $request
            );

            // Una modificacion solo del valor medido no cambia el teorico
            // posterior: el sistema encadena saldos TEORICOS, no mediciones.
            // Al insertar una fecha faltante o variar el teorico historico,
            // si se deben mantener coherentes las fechas posteriores.
            $cambioTeorico = ! $existente
                || $existente->saldo_teorico_gal === null
                || round((float) $existente->saldo_teorico_gal, 4)
                    !== round((float) $calculo['saldo_teorico'], 4);

            $actualizados = 0;
            if ($cambioTeorico) {
                $actualizados = $this->actualizarSaldosPosteriores(
                    (int) $tanque->id,
                    $fecha,
                    (float) $calculo['saldo_teorico'],
                    $request
                );
            }

            return [$accion, $actualizados];
        }, 3);

        $mensaje = $accion === 'ACTUALIZAR'
            ? 'Medicion de tanque actualizada correctamente.'
            : 'Medicion de tanque registrada correctamente.';

        if ($posteriores > 0) {
            $mensaje .= ' Se actualizaron '.$posteriores.' controles posteriores del mismo tanque.';
        }

        return back()
            ->with('exito', $mensaje)
            ->with('tanques_tanque_id', (int) $validados['tanque_id'])
            ->with('tanques_fecha_control', $validados['fecha_control']);
    }

    /**
     * Reencadena saldos teoricos solamente si una fecha historica insertada
     * o corregida altera el saldo teorico de las fechas siguientes.
     * Conserva ingresos, salidas y mediciones fisicas previamente guardadas.
     * Todas las modificaciones quedan auditadas en la misma transaccion.
     */
    private function actualizarSaldosPosteriores(
        int $tanqueId,
        string $fecha,
        float $saldoAnterior,
        Request $request
    ): int {
        $siguientes = DB::table('controles_diarios_tanque')
            ->where('tanque_id', $tanqueId)
            ->where('fecha_control', '>', $fecha)
            ->orderBy('fecha_control')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $modificados = 0;
        foreach ($siguientes as $control) {
            $nuevo = round(
                $saldoAnterior
                    + (float) $control->ingreso_gal
                    - (float) $control->salida_total_gal,
                4
            );
            $diferencia = $control->saldo_medido_gal === null
                ? null
                : round((float) $control->saldo_medido_gal - $nuevo, 4);

            $teoricoIgual = $control->saldo_teorico_gal !== null
                && round((float) $control->saldo_teorico_gal, 4) === $nuevo;
            $diferenciaIgual = $control->diferencia_gal === null
                ? $diferencia === null
                : ($diferencia !== null
                    && round((float) $control->diferencia_gal, 4) === $diferencia);

            if ($teoricoIgual && $diferenciaIgual) {
                // Se extinguio el cambio; el resto de la cadena no varia.
                break;
            }

            $cambios = [
                'saldo_teorico_gal' => $nuevo,
                'diferencia_gal' => $diferencia,
            ];

            DB::table('controles_diarios_tanque')
                ->where('id', $control->id)
                ->update($cambios);

            ServicioAuditoria::registrar(
                'CONTROL_TANQUE',
                $control->id,
                'ACTUALIZAR',
                (array) $control,
                array_merge((array) $control, $cambios),
                $request
            );

            $saldoAnterior = $nuevo;
            $modificados++;
        }

        return $modificados;
    }
}
