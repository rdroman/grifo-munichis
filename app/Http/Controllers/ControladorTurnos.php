<?php

namespace App\Http\Controllers;



use App\Support\PermisosSupervisor;
use App\Support\AccesoGastosSupervisor;
use App\Services\ServicioAuditoria;
use App\Services\ServicioSecuenciaTurnos;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ControladorTurnos extends ControladorBaseGrifo
{
    public function indice(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR','CONTABILIDAD','LECTURA']);

        $desde = $request->query('desde', now()->subDays(14)->toDateString());
        $hasta = $request->query('hasta', now()->toDateString());
        $codigo = $request->query('turno');
        $trabajador = $request->query('trabajador');

        $q = DB::table('turnos_operativos as t')
            ->leftJoin('tipos_turno as tt','tt.id','=','t.tipo_turno_id')
            ->leftJoin('trabajadores as tr','tr.id','=','t.trabajador_id')
            ->leftJoin('resumen_financiero_turno as rf','rf.turno_operativo_id','=','t.id')
            ->where('t.establecimiento_id',$this->establecimientoId())
            ->select(
                't.id','t.fecha_operacion','tt.codigo as codigo_turno','tt.nombre as nombre_turno',
                'tr.nombre_completo as trabajador','rf.ventas_brutas_combustible','rf.gastos_efectivo',
                'rf.neto_esperado','rf.efectivo_entregado','rf.diferencia_operador','rf.diferencia_caja',
                'rf.estado_validacion_digital'
            );

        $this->aplicarAlcanceTurnosOperador($q, 't');

        if (Esquema::tiene('turnos_operativos','motivo_turno_id') && Esquema::existeTabla('motivos_turno')) {
            $q->leftJoin('motivos_turno as mt','mt.id','=','t.motivo_turno_id')
                ->addSelect('mt.codigo as codigo_motivo','mt.nombre as motivo');
        }

        if ($desde) $q->whereDate('t.fecha_operacion','>=',$desde);
        if ($hasta) $q->whereDate('t.fecha_operacion','<=',$hasta);
        if ($codigo) $q->where('tt.codigo',$codigo);

        if (! $this->esOperador() && $trabajador) {
            $q->where('tr.id',$trabajador);
        }

        $turnos = $q->orderByDesc('t.fecha_operacion')->orderByDesc('t.id')->paginate(25)->withQueryString();
        $tipos = DB::table('tipos_turno')->orderBy('id')->get();

        if ($this->esOperador()) {
            $trabajadorId = $this->exigirTrabajadorVinculado();
            $trabajadores = DB::table('trabajadores')->where('id',$trabajadorId)->get(['id','nombre_completo']);
            $trabajador = $trabajadorId;
        } else {
            $trabajadores = DB::table('trabajadores')
                ->where('establecimiento_id',$this->establecimientoId())
                ->orderBy('nombre_completo')
                ->get(['id','nombre_completo']);
        }

        $pendientesQ = DB::table('resumen_financiero_turno as rf')
            ->join('turnos_operativos as t','t.id','=','rf.turno_operativo_id')
            ->where('t.establecimiento_id',$this->establecimientoId())
            ->where('rf.estado_validacion_digital','PENDIENTE');
        $this->aplicarAlcanceTurnosOperador($pendientesQ, 't');
        $pendientes = $pendientesQ->count();

        $esOperador = $this->esOperador();

                        // [PARCHE_LISTADO_FINANCIERO_V4]
        // El listado usa la misma fuente de verdad que el detalle del turno.
        // Se calcula en memoria y NO se modifica la base de datos al consultar.
        $coleccionTurnosFinanciero = method_exists($turnos, 'getCollection')
            ? $turnos->getCollection()
            : collect($turnos);

        $idsTurnosFinanciero = $coleccionTurnosFinanciero
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $resumenesFinanciero = $idsTurnosFinanciero->isEmpty()
            ? collect()
            : DB::table('resumen_financiero_turno')
                ->whereIn('turno_operativo_id', $idsTurnosFinanciero)
                ->get()
                ->keyBy('turno_operativo_id');

        $servicioFinancieroListado = app(\App\Services\ServicioFinancieroTurno::class);

        foreach ($coleccionTurnosFinanciero as $filaTurno) {
            $rfListado = $resumenesFinanciero->get((int) $filaTurno->id);

            try {
                $calculoListado = $servicioFinancieroListado->calcular((int) $filaTurno->id);

                // VENTA del listado = dinero generado antes de gastos:
                // neto de combustible + otros ingresos + lubricantes.
                $ventaListado = round(
                    (float) ($calculoListado['monto_neto_combustible'] ?? 0)
                    + (float) ($calculoListado['otros_ingresos'] ?? 0)
                    + (float) ($calculoListado['ventas_lubricantes'] ?? 0),
                    2
                );

                $gastoListado = round((float) ($calculoListado['gastos'] ?? 0), 2);
                $netoListado = round((float) ($calculoListado['monto_a_entregar'] ?? 0), 2);
            } catch (\Throwable $e) {
                // Fallback defensivo para que un registro historico anomalo no bloquee todo el listado.
                $ventaListado = round(
                    (float) ($rfListado->monto_neto_combustible ?? $rfListado->ventas_brutas_combustible ?? 0)
                    + (float) ($rfListado->otros_ingresos ?? 0)
                    + (float) ($rfListado->ventas_lubricantes ?? 0),
                    2
                );
                $gastoListado = round((float) ($rfListado->gastos_efectivo ?? 0), 2);
                $netoListado = round($ventaListado - $gastoListado, 2);
                $filaTurno->calculo_financiero_error = $e->getMessage();
            }

            // Conciliacion: si efectivo y digital ya estan validados, se usa administracion.
            // De lo contrario se usa lo informado por el operador, con fallback NULL/0 al
            // valor administrativo del mismo componente para datos historicos.
            $efectivoOperadorOriginal = $rfListado->efectivo_entregado ?? null;
            $digitalOperadorOriginal = $rfListado->digital_operador ?? null;
            $efectivoAdmin = $rfListado->efectivo_administrador ?? null;
            $digitalAdmin = $rfListado->digital_administrador ?? null;

            $efectivoOperador = (
                ($efectivoOperadorOriginal === null || (float) $efectivoOperadorOriginal == 0.0)
                && $efectivoAdmin !== null
            )
                ? (float) $efectivoAdmin
                : (float) ($efectivoOperadorOriginal ?? 0);

            $digitalOperador = (
                ($digitalOperadorOriginal === null || (float) $digitalOperadorOriginal == 0.0)
                && $digitalAdmin !== null
            )
                ? (float) $digitalAdmin
                : (float) ($digitalOperadorOriginal ?? 0);

            $montoOperador = round($efectivoOperador + $digitalOperador, 2);

            $hayValidacionAdministrativa = $efectivoAdmin !== null || $digitalAdmin !== null;
            $montoAdmin = $hayValidacionAdministrativa
                ? round(
                    (float) ($efectivoAdmin ?? $efectivoOperador)
                    + (float) ($digitalAdmin ?? $digitalOperador),
                    2
                )
                : null;

            $turnoValidado =
                ($rfListado->validado_efectivo_en ?? null) !== null
                && ($rfListado->estado_validacion_digital ?? null) === 'VALIDADO';

            $montoBase = ($turnoValidado && $montoAdmin !== null)
                ? $montoAdmin
                : $montoOperador;

            $diferenciaListado = round($montoBase - $netoListado, 2);

            // Aliases actuales y legacy: la vista existente puede leer cualquiera de ellos.
            $filaTurno->venta = $ventaListado;
            $filaTurno->ventas = $ventaListado;
            $filaTurno->ventas_brutas_combustible = $ventaListado;

            $filaTurno->gasto = $gastoListado;
            $filaTurno->gastos = $gastoListado;
            $filaTurno->gastos_efectivo = $gastoListado;

            $filaTurno->neto = $netoListado;
            $filaTurno->neto_esperado = $netoListado;
            $filaTurno->monto_a_entregar = $netoListado;

            $filaTurno->diferencia_conciliada = $diferenciaListado;
            $filaTurno->diferencia = $diferenciaListado;
            $filaTurno->diferencia_operador = $diferenciaListado;
            $filaTurno->diferencia_caja = $diferenciaListado;
        }

        $productosReinicio = collect();
        $reinicioPendiente = null;
        $productosReinicioPendiente = collect();

        if (auth()->user()->rol === 'ADMINISTRADOR') {
            $productosReinicio = DB::table('productos as p')
                ->join('mangueras as m', 'm.producto_id', '=', 'p.id')
                ->join('surtidores as s', 's.id', '=', 'm.surtidor_id')
                ->where('s.establecimiento_id', $this->establecimientoId())
                ->where(function ($q) {
                    $q->whereNull('m.activo')->orWhere('m.activo', 1);
                })
                ->where(function ($q) {
                    $q->whereNull('s.activo')->orWhere('s.activo', 1);
                })
                ->groupBy('p.id', 'p.codigo', 'p.nombre')
                ->orderBy('p.id')
                ->get([
                    'p.id',
                    'p.codigo',
                    'p.nombre',
                    DB::raw('COUNT(m.id) as cantidad_mangueras'),
                ]);

            if (Esquema::existeTabla('reinicios_totalizador')) {
                $reinicioPendiente = DB::table('reinicios_totalizador')
                    ->where('establecimiento_id', $this->establecimientoId())
                    ->where('estado', 'PENDIENTE')
                    ->orderByDesc('id')
                    ->first();

                if (
                    $reinicioPendiente
                    && Esquema::existeTabla('reinicios_totalizador_detalle')
                ) {
                    $productosReinicioPendiente = DB::table('reinicios_totalizador_detalle as d')
                        ->join('productos as p', 'p.id', '=', 'd.producto_id')
                        ->where('d.reinicio_totalizador_id', $reinicioPendiente->id)
                        ->distinct()
                        ->orderBy('p.id')
                        ->get(['p.id', 'p.codigo', 'p.nombre']);
                }
            }
        }

        return view('turnos.indice', compact(
            'turnos',
            'tipos',
            'trabajadores',
            'desde',
            'hasta',
            'codigo',
            'trabajador',
            'pendientes',
            'esOperador',
            'productosReinicio',
            'reinicioPendiente',
            'productosReinicioPendiente'
        ));
    }


    public function crear()
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR']);

        $modoRegistro = request()->query('modo') === 'extraordinario'
            ? 'extraordinario'
            : 'regular';

        if ($modoRegistro === 'extraordinario') {
            $this->autorizar(['ADMINISTRADOR']);
        }

        $secuencia = app(ServicioSecuenciaTurnos::class)
            ->resolver($this->establecimientoId(), $modoRegistro);

        if (! ($secuencia['disponible'] ?? false)) {
            return redirect()
                ->route('turnos.indice')
                ->withErrors([
                    'turno' => $secuencia['mensaje'] ?? 'No existe un turno disponible para registrar.',
                ]);
        }

        $tipos = DB::table('tipos_turno')->orderBy('id')->get();
        $motivos = Esquema::existeTabla('motivos_turno')
            ? DB::table('motivos_turno')->orderBy('id')->get()
            : collect();
        $motivoTurnoNormalId = $modoRegistro === 'regular'
            ? ($motivos->first(fn ($motivo) => strtoupper(trim((string) $motivo->codigo)) === 'NORMAL')?->id
                ?? $motivos->first(fn ($motivo) => mb_strtolower(trim((string) $motivo->nombre)) === 'turno normal')?->id)
            : null;

        if ($this->esOperador()) {
            $trabajadorId = $this->exigirTrabajadorVinculado();
            $trabajadores = DB::table('trabajadores')->where('id',$trabajadorId)->get();
        } else {
            $trabajadores = DB::table('trabajadores')
                ->where('establecimiento_id',$this->establecimientoId())
                ->where('activo',1)
                ->orderBy('nombre_completo')
                ->get();
        }

        $reinicioPendiente = null;
        $basesReinicio = collect();

        if (Esquema::existeTabla('reinicios_totalizador')) {
            $reinicioPendiente = DB::table('reinicios_totalizador')
                ->where('establecimiento_id', $this->establecimientoId())
                ->where('estado', 'PENDIENTE')
                ->orderByDesc('id')
                ->first();

            if (
                $reinicioPendiente
                && Esquema::existeTabla('reinicios_totalizador_detalle')
            ) {
                $basesReinicio = DB::table('reinicios_totalizador_detalle')
                    ->where('reinicio_totalizador_id', $reinicioPendiente->id)
                    ->pluck('lectura_base', 'manguera_id');
            }
        }

        $mangueras = DB::table('mangueras as m')
            ->join('surtidores as s', 's.id', '=', 'm.surtidor_id')
            ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
            ->where('s.establecimiento_id', $this->establecimientoId())
            ->where(function ($q) {
                $q->whereNull('m.activo')
                    ->orWhere('m.activo', 1);
            })
            ->where(function ($q) {
                $q->whereNull('s.activo')
                    ->orWhere('s.activo', 1);
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

        foreach ($mangueras as $manguera) {
            $ultimaLectura = DB::table('lecturas_totalizador as l')
                ->join('turnos_operativos as t', 't.id', '=', 'l.turno_operativo_id')
                ->where('t.establecimiento_id', $this->establecimientoId())
                ->where('l.manguera_id', $manguera->id)
                ->orderByDesc('t.fecha_operacion')
                ->orderByDesc('t.id')
                ->orderByDesc('l.id')
                ->select(
                    'l.lectura',
                    't.fecha_operacion',
                    't.id as turno_operativo_id'
                )
                ->first();

            $manguera->reinicio_pendiente = $basesReinicio->has($manguera->id);

            if ($manguera->reinicio_pendiente) {
                $manguera->lectura_anterior = round(
                    (float) $basesReinicio->get($manguera->id),
                    3
                );
                $manguera->fecha_lectura_anterior = null;
                $manguera->turno_lectura_anterior = null;
            } else {
                $manguera->lectura_anterior = $ultimaLectura?->lectura;
                $manguera->fecha_lectura_anterior = $ultimaLectura?->fecha_operacion;
                $manguera->turno_lectura_anterior = $ultimaLectura?->turno_operativo_id;
            }
        }

        $esOperador = $this->esOperador();

        return view('turnos.formulario', compact(
            'tipos',
            'motivos',
            'trabajadores',
            'mangueras',
            'esOperador',
            'modoRegistro',
            'secuencia',
            'reinicioPendiente',
            'motivoTurnoNormalId'
        ));
    }

    public function guardar(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR']);

        $modoRegistro = (string) $request->input('modo_registro', 'regular');

        if (! in_array($modoRegistro, ['regular','extraordinario'], true)) {
            throw ValidationException::withMessages([
                'tipo_turno_id' => 'Modo de registro de turno no válido.',
            ]);
        }

        if ($modoRegistro === 'extraordinario') {
            $this->autorizar(['ADMINISTRADOR']);
        }

        $secuenciaEsperada = app(ServicioSecuenciaTurnos::class)
            ->resolver($this->establecimientoId(), $modoRegistro);

        if (! ($secuenciaEsperada['disponible'] ?? false)) {
            throw ValidationException::withMessages([
                'tipo_turno_id' => $secuenciaEsperada['mensaje']
                    ?? 'No existe un turno disponible para registrar.',
            ]);
        }

        if (
            (string) $request->input('fecha_operacion') !== (string) $secuenciaEsperada['fecha']
            || (int) $request->input('tipo_turno_id') !== (int) $secuenciaEsperada['tipo_turno_id']
        ) {
            throw ValidationException::withMessages([
                'tipo_turno_id' =>
                    'No se puede saltar la secuencia de turnos. El siguiente registro permitido es '
                    . $secuenciaEsperada['codigo'] . ' del '
                    . \Carbon\Carbon::parse($secuenciaEsperada['fecha'])->format('d/m/Y') . '.',
            ]);
        }

        $request->validate([
            'fecha_operacion'=>['required','date'],
            'tipo_turno_id'=>['required',Rule::exists('tipos_turno','id')],
            'trabajador_id'=>['nullable',Rule::exists('trabajadores','id')],
            'motivo_turno_id'=> Esquema::existeTabla('motivos_turno') ? ['nullable',Rule::exists('motivos_turno','id')] : ['nullable'],
            //'lecturas'=>['nullable','array'],
            'lecturas' => ['required', 'array'],
            'lecturas.*.lectura' => [
                'required',
                'numeric',
                'min:0',
            ],
            //'ventas_brutas_combustible'=>['nullable','numeric'],
            'otros_ingresos'=>['nullable','numeric'],
            'ventas_lubricantes'=>['nullable','numeric'],
            'gastos_efectivo'=>['nullable','numeric'],
            'ultimo_saldo_bancario'=>['nullable','numeric'],
            'observaciones'=>['nullable','string','max:3000'],
        ]);

        $trabajadorId = $this->esOperador()
            ? $this->exigirTrabajadorVinculado()
            : ($request->trabajador_id ?: null);

        $turnoId = DB::transaction(function() use ($request, $trabajadorId, $modoRegistro) {
            DB::table('turnos_operativos')
                ->where('establecimiento_id', $this->establecimientoId())
                ->orderByDesc('fecha_operacion')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $secuenciaEnTransaccion = app(ServicioSecuenciaTurnos::class)
                ->resolver($this->establecimientoId(), $modoRegistro, true);

            if (
                ! ($secuenciaEnTransaccion['disponible'] ?? false)
                || (string) $request->fecha_operacion !== (string) $secuenciaEnTransaccion['fecha']
                || (int) $request->tipo_turno_id !== (int) $secuenciaEnTransaccion['tipo_turno_id']
            ) {
                throw ValidationException::withMessages([
                    'tipo_turno_id' =>
                        'La secuencia de turnos cambió mientras se registraba. '
                        . 'Vuelva al listado y abra nuevamente el formulario.',
                ]);
            }

            $reinicioPendiente = null;
            $basesReinicio = collect();

            if (Esquema::existeTabla('reinicios_totalizador')) {
                $reinicioPendiente = DB::table('reinicios_totalizador')
                    ->where('establecimiento_id', $this->establecimientoId())
                    ->where('estado', 'PENDIENTE')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (
                    $reinicioPendiente
                    && Esquema::existeTabla('reinicios_totalizador_detalle')
                ) {
                    $basesReinicio = DB::table('reinicios_totalizador_detalle')
                        ->where('reinicio_totalizador_id', $reinicioPendiente->id)
                        ->pluck('lectura_base', 'manguera_id');
                }
            }

            $motivoTurnoId = $request->motivo_turno_id ?: null;
            if (Esquema::tiene('turnos_operativos', 'motivo_turno_id') && ! $motivoTurnoId && Esquema::existeTabla('motivos_turno')) {
                $motivoTurnoId = DB::table('motivos_turno')
                    ->where('codigo', 'NORMAL')
                    ->value('id') ?: DB::table('motivos_turno')->orderBy('id')->value('id');
            }

            $datosTurno = [
                'establecimiento_id'=>$this->establecimientoId(),
                'fecha_operacion'=>$request->fecha_operacion,
                'tipo_turno_id'=>$request->tipo_turno_id,
                'trabajador_id'=>$trabajadorId,
                'motivo_turno_id'=>$motivoTurnoId,
                'observaciones'=>$request->observaciones ?: null,
                'hoja_origen'=>'SISTEMA',
                'creado_en'=>now(),
            ];
            $datosTurno = Esquema::filtrar('turnos_operativos',$datosTurno);

            try {
                $turnoId = DB::table('turnos_operativos')->insertGetId($datosTurno);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'tipo_turno_id'=>'Ya existe un turno incompatible con la fecha/tipo seleccionado, o faltan datos obligatorios del turno.'
                ]);
            }
            
            $sumasProducto = [];

            $colLectura = Esquema::resolver(
                'lecturas_totalizador',
                ['lectura', 'totalizador']
            );

            $colGalones = Esquema::resolver(
                'lecturas_totalizador',
                ['galones_vendidos', 'galones']
            );

            /*
            |--------------------------------------------------------------------------
            | Mangueras válidas del establecimiento
            |--------------------------------------------------------------------------
            */

            $manguerasActivas = DB::table('mangueras as m')
                ->join(
                    'surtidores as s',
                    's.id',
                    '=',
                    'm.surtidor_id'
                )
                ->where(
                    's.establecimiento_id',
                    $this->establecimientoId()
                )
                ->where(function ($q) {
                    $q->whereNull('m.activo')
                    ->orWhere('m.activo', 1);
                })
                ->where(function ($q) {
                    $q->whereNull('s.activo')
                    ->orWhere('s.activo', 1);
                })
                ->select(
                    'm.id',
                    'm.codigo',
                    'm.producto_id'
                )
                ->orderBy('m.id')
                ->get();

            $lecturasFormulario =
                (array) $request->input('lecturas', []);

            /*
            |--------------------------------------------------------------------------
            | Calcular galones por diferencia de totalizadores
            |--------------------------------------------------------------------------
            */

            foreach ($manguerasActivas as $manguera) {

                $fila = $lecturasFormulario[
                    $manguera->id
                ] ?? [];

                $lecturaFinal =
                    $fila['lectura'] ?? null;

                if (
                    $lecturaFinal === null
                    || $lecturaFinal === ''
                ) {
                    throw ValidationException::withMessages([
                        'lecturas.' . $manguera->id . '.lectura' =>
                            'Debe registrar la lectura final de la manguera '
                            . $manguera->codigo . '.'
                    ]);
                }

                $lecturaFinal =
                    round((float) $lecturaFinal, 3);

                /*
                |--------------------------------------------------------------------------
                | Buscar lectura inmediatamente anterior
                |--------------------------------------------------------------------------
                |
                | El turno actual ya existe, pero todavía no tiene lecturas.
                |
                | Para fechas anteriores usamos fecha_operacion.
                | Para la misma fecha usamos el ID como secuencia real del corte.
                |
                */

                $lecturaAnterior = DB::table(
                    'lecturas_totalizador as l'
                )
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
                    ->where(function ($q) use (
                        $request,
                        $turnoId
                    ) {
                        $q->where(
                            't.fecha_operacion',
                            '<',
                            $request->fecha_operacion
                        )
                        ->orWhere(function ($qMismaFecha) use (
                            $request,
                            $turnoId
                        ) {
                            $qMismaFecha
                                ->where(
                                    't.fecha_operacion',
                                    '=',
                                    $request->fecha_operacion
                                )
                                ->where(
                                    't.id',
                                    '<',
                                    $turnoId
                                );
                        });
                    })
                    ->orderByDesc('t.fecha_operacion')
                    ->orderByDesc('t.id')
                    ->orderByDesc('l.id')
                    ->value('l.lectura');

                if ($basesReinicio->has($manguera->id)) {
                    $lecturaAnterior = round(
                        (float) $basesReinicio->get($manguera->id),
                        3
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | No inventar una lectura anterior
                |--------------------------------------------------------------------------
                */

                if ($lecturaAnterior === null) {

                    throw ValidationException::withMessages([
                        'lecturas.' . $manguera->id . '.lectura' =>
                            'No existe una lectura anterior para la manguera '
                            . $manguera->codigo
                            . '. Se debe establecer primero su lectura inicial.'
                    ]);
                }

                $lecturaAnterior =
                    round((float) $lecturaAnterior, 3);

                /*
                |--------------------------------------------------------------------------
                | La lectura final no puede retroceder
                |--------------------------------------------------------------------------
                */

                if ($lecturaFinal < $lecturaAnterior) {

                    throw ValidationException::withMessages([
                        'lecturas.' . $manguera->id . '.lectura' =>
                            'La lectura final de '
                            . $manguera->codigo
                            . ' (' . number_format(
                                $lecturaFinal,
                                3,
                                '.',
                                ''
                            ) . ') no puede ser menor que la lectura anterior ('
                            . number_format(
                                $lecturaAnterior,
                                3,
                                '.',
                                ''
                            ) . ').'
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | GALONES VENDIDOS
                |--------------------------------------------------------------------------
                */

                $galones = round(
                    $lecturaFinal - $lecturaAnterior,
                    3
                );

                /*
                |--------------------------------------------------------------------------
                | Guardar lectura
                |--------------------------------------------------------------------------
                */

                $datos = [
                    'turno_operativo_id' => $turnoId,
                    'manguera_id' => (int) $manguera->id,
                    'hoja_origen' => 'SISTEMA',
                    'creado_en' => now(),
                ];

                if ($colLectura) {
                    $datos[$colLectura] =
                        $lecturaFinal;
                }

                if ($colGalones) {
                    $datos[$colGalones] =
                        $galones;
                }

                DB::table('lecturas_totalizador')
                    ->insert(
                        Esquema::filtrar(
                            'lecturas_totalizador',
                            $datos
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | Acumular por producto
                |--------------------------------------------------------------------------
                */

                $sumasProducto[
                    $manguera->producto_id
                ] =
                    (
                        $sumasProducto[
                            $manguera->producto_id
                        ] ?? 0
                    )
                    + $galones;
            }
            // $sumasProducto = [];
            // $colLectura = Esquema::resolver('lecturas_totalizador',['lectura','totalizador']);
            // $colGalones = Esquema::resolver('lecturas_totalizador',['galones_vendidos','galones']);

            // foreach ((array)$request->input('lecturas',[]) as $mangueraId=>$fila) {
            //     $lectura = $fila['lectura'] ?? null;
            //     $galones = $fila['galones'] ?? null;
            //     if ($lectura === null || $lectura === '') continue;

            //     $manguera = DB::table('mangueras')->where('id',$mangueraId)->first();
            //     if (! $manguera) continue;

            //     $datos = [
            //         'turno_operativo_id'=>$turnoId,
            //         'manguera_id'=>(int)$mangueraId,
            //         'hoja_origen'=>'SISTEMA',
            //         'creado_en'=>now(),
            //     ];
            //     if ($colLectura) $datos[$colLectura]=(float)$lectura;
            //     if ($colGalones && $galones !== null && $galones !== '') $datos[$colGalones]=(float)$galones;

            //     DB::table('lecturas_totalizador')->insert(Esquema::filtrar('lecturas_totalizador',$datos));

            //     if ($galones !== null && $galones !== '') {
            //         $sumasProducto[$manguera->producto_id] = ($sumasProducto[$manguera->producto_id] ?? 0) + (float)$galones;
            //     }
            // }

            // if (Esquema::existeTabla('totales_producto_turno')) {
            //     foreach ($sumasProducto as $productoId=>$galones) {
            //         DB::table('totales_producto_turno')->insert(Esquema::filtrar('totales_producto_turno',[
            //             'turno_operativo_id'=>$turnoId,
            //             'producto_id'=>$productoId,
            //             'galones'=>$galones,
            //         ]));
            //     }
            // }
            //$ventas=(float)$request->input('ventas_brutas_combustible',0);
            $ventasExactas = 0.0;
            $detallesProducto = [];

            $servicioPrecios = app(\App\Services\ServicioPrecioVenta::class);

            foreach ($sumasProducto as $productoId => $galones) {

                $galones = (float) $galones;

                $precioVigente = $servicioPrecios->obtenerParaTurno(
                    $this->establecimientoId(),
                    (int) $productoId,
                    (int) $turnoId
                );

                if (! $precioVigente) {
                    $producto = DB::table('productos')
                        ->where('id', $productoId)
                        ->value('nombre');

                    throw ValidationException::withMessages([
                        'lecturas' => 'No existe un precio de venta vigente para '
                            . ($producto ?: 'el producto ID '.$productoId)
                            . ' en este turno.'
                    ]);
                }

                $precio = (float) $precioVigente->precio;
                $importeExacto = $galones * $precio;

                $ventasExactas += $importeExacto;

                $detallesProducto[] = [
                    'producto_id' => (int) $productoId,
                    'galones' => $galones,
                    'precio' => $precio,
                    'importe_exacto' => $importeExacto,
                    'importe_bruto' => round($importeExacto, 2),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Total oficial de ventas de combustible
            |--------------------------------------------------------------------------
            |
            | Se suman primero los importes con toda su precisión y se redondea
            | una sola vez al final.
            |
            */
            $ventas = round($ventasExactas, 2);

            /*
            |--------------------------------------------------------------------------
            | Ajuste de redondeo
            |--------------------------------------------------------------------------
            |
            | Como importe_bruto almacena solamente 2 decimales, la suma de los
            | subtotales redondeados podría diferir en S/ 0.01 del total oficial.
            | El residuo se aplica al producto con mayor importe para mantener:
            |
            | SUM(importe_bruto) = ventas_brutas_combustible
            |
            */
            if (count($detallesProducto) > 0) {

                $sumaRedondeada = round(
                    array_sum(
                        array_column($detallesProducto, 'importe_bruto')
                    ),
                    2
                );

                $ajusteRedondeo = round($ventas - $sumaRedondeada, 2);

                if (abs($ajusteRedondeo) >= 0.01) {

                    $indiceMayor = 0;

                    foreach ($detallesProducto as $indice => $detalle) {
                        if (
                            $detalle['importe_exacto']
                            > $detallesProducto[$indiceMayor]['importe_exacto']
                        ) {
                            $indiceMayor = $indice;
                        }
                    }

                    $detallesProducto[$indiceMayor]['importe_bruto'] = round(
                        $detallesProducto[$indiceMayor]['importe_bruto']
                        + $ajusteRedondeo,
                        2
                    );
                }
            }

            if (Esquema::existeTabla('totales_producto_turno')) {

                foreach ($detallesProducto as $detalle) {

                    DB::table('totales_producto_turno')->insert(
                        Esquema::filtrar(
                            'totales_producto_turno',
                            [
                                'turno_operativo_id' => $turnoId,
                                'producto_id' => $detalle['producto_id'],
                                'galones' => $detalle['galones'],
                                'importe_bruto' => $detalle['importe_bruto'],
                            ]
                        )
                    );
                }
            }


            $otros=(float)$request->input('otros_ingresos',0);
            $lub=(float)$request->input('ventas_lubricantes',0);
            $gastos=(float)$request->input('gastos_efectivo',0);

            $rf=[
                'turno_operativo_id'=>$turnoId,
                'ventas_brutas_combustible'=>$ventas,
                'otros_ingresos'=>$otros,
                'ventas_lubricantes'=>$lub,
                'gastos_efectivo'=>$gastos,
                'neto_esperado'=>$ventas+$otros+$lub-$gastos,
                'efectivo_entregado'=>0,
                'digital_operador'=>0,
                'digital_entregado'=>0,
                'ultimo_saldo_bancario'=>$request->filled('ultimo_saldo_bancario')?(float)$request->ultimo_saldo_bancario:null,
                'hoja_origen'=>'SISTEMA',
            ];

            if (Esquema::tiene('resumen_financiero_turno','estado_validacion_digital')) {
                $rf['estado_validacion_digital'] = 'PENDIENTE';
            }

            DB::table('resumen_financiero_turno')->insert(Esquema::filtrar('resumen_financiero_turno',$rf));

            if ($reinicioPendiente) {
                DB::table('reinicios_totalizador')
                    ->where('id', $reinicioPendiente->id)
                    ->update([
                        'estado' => 'APLICADO',
                        'turno_operativo_id' => $turnoId,
                        'aplicado_en' => now('America/Lima'),
                    ]);

                ServicioAuditoria::registrar(
                    'REINICIO_TOTALIZADOR',
                    $reinicioPendiente->id,
                    'ACTUALIZAR',
                    [
                        'estado' => 'PENDIENTE',
                        'turno_operativo_id' => null,
                    ],
                    [
                        'estado' => 'APLICADO',
                        'turno_operativo_id' => $turnoId,
                    ],
                    $request
                );
            }

            ServicioAuditoria::registrar('TURNO_OPERATIVO',$turnoId,'CREAR',null,$datosTurno,$request);

            return $turnoId;
        });

                // [PARCHE_FINANCIERO_TURNOS] normaliza el resumen con la nueva lógica.
        app(\App\Services\ServicioFinancieroTurno::class)->sincronizar((int) $turnoId);
        return redirect()->route('turnos.mostrar',$turnoId)->with('exito','Turno registrado correctamente. Registra la entrega de efectivo y dinero digital al finalizar el turno.');
    }

    public function mostrar(int $turno)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','OPERADOR','CONTABILIDAD','LECTURA','SUPERVISOR']);

        $cabecera = DB::table('turnos_operativos as t')
            ->leftJoin('tipos_turno as tt','tt.id','=','t.tipo_turno_id')
            ->leftJoin('trabajadores as tr','tr.id','=','t.trabajador_id')
            ->where('t.id',$turno)
            ->where('t.establecimiento_id',$this->establecimientoId())
            ->select('t.*','tt.codigo as codigo_turno','tt.nombre as nombre_turno','tr.nombre_completo as trabajador')
            ->first();

        abort_unless($cabecera,404);

        if ($this->esOperador()) {
            abort_unless((int)$cabecera->trabajador_id === $this->exigirTrabajadorVinculado(), 404);
        }

        if (Esquema::tiene('turnos_operativos','motivo_turno_id') && $cabecera->motivo_turno_id) {
            $cabecera->motivo = DB::table('motivos_turno')->where('id',$cabecera->motivo_turno_id)->value('nombre');
        }

        $financiero = DB::table('resumen_financiero_turno')->where('turno_operativo_id',$turno)->first();

        // [PARCHE_FINANCIERO_TURNOS] cálculo no destructivo para la pantalla de detalle.
        $calculoFinanciero = app(\App\Services\ServicioFinancieroTurno::class)->calcular((int) $turno);

        $lecturas = DB::table('lecturas_totalizador as l')
            ->leftJoin('mangueras as m','m.id','=','l.manguera_id')
            ->leftJoin('productos as p','p.id','=','m.producto_id')
            ->where('l.turno_operativo_id',$turno)
            ->select('l.*','m.codigo as manguera','p.codigo as producto_codigo','p.nombre as producto')
            ->orderBy('m.id')
            ->get();

        $totales = DB::table('totales_producto_turno as x')
            ->leftJoin('productos as p','p.id','=','x.producto_id')
            ->where('x.turno_operativo_id',$turno)
            ->select('x.*','p.codigo as producto_codigo','p.nombre as producto')
            ->get();

        $gastos = Esquema::existeTabla('gastos')
            ? AccesoGastosSupervisor::filtrar(
                DB::table('gastos')->where('turno_operativo_id', $turno), 'gastos'
              )->orderByDesc('id')->get()
            : collect();

        $columnasGasto = [
            'fecha' => Esquema::resolver('gastos',['fecha_gasto','fecha']),
            'detalle' => Esquema::resolver('gastos',['detalle']),
            'monto' => Esquema::resolver('gastos',['monto','importe']),
        ];

        $creditos = Esquema::existeTabla('ventas_credito')
            ? DB::table('ventas_credito as v')
                ->leftJoin('clientes as c','c.id','=','v.cliente_id')
                ->where('v.turno_operativo_id',$turno)
                ->select('v.*','c.nombre as cliente')
                ->orderByDesc('v.id')
                ->get()
            : collect();

        $totalesCreditos = [];
        if ($creditos->count() && Esquema::existeTabla('detalle_ventas_credito')) {
            $colCantidad = Esquema::resolver('detalle_ventas_credito',['cantidad_galones','cantidad_gal','galones','cantidad']);
            $colPrecio = Esquema::resolver('detalle_ventas_credito',['precio_unitario','precio']);
            $colImporte = Esquema::resolver('detalle_ventas_credito',['importe','monto','total']);

            foreach ($creditos as $credito) {
                $items = DB::table('detalle_ventas_credito')->where('venta_credito_id',$credito->id)->get();
                $totalesCreditos[$credito->id] = $items->sum(function ($item) use ($colCantidad,$colPrecio,$colImporte) {
                    if ($colImporte) return (float)($item->{$colImporte} ?? 0);
                    return (float)($item->{$colCantidad} ?? 0) * (float)($item->{$colPrecio} ?? 0);
                });
            }
        }

        $efectivoFinal = (float)($financiero?->efectivo_entregado ?? 0);
        if ($financiero && Esquema::tiene('resumen_financiero_turno','efectivo_administrador') && $financiero->efectivo_administrador !== null) {
            $efectivoFinal = (float)$financiero->efectivo_administrador;
        }

        $digitalFinal = (float)($financiero?->digital_operador ?? $financiero?->digital_entregado ?? 0);
        if ($financiero && Esquema::tiene('resumen_financiero_turno','digital_administrador') && $financiero->digital_administrador !== null) {
            $digitalFinal = (float)$financiero->digital_administrador;
        }

        $esOperador = $this->esOperador();
        $puedeCorregirEfectivo = auth()->user()->rol === 'ADMINISTRADOR';
        $puedeValidarDigital = in_array(auth()->user()->rol,['ADMINISTRADOR','GERENCIA','CONTABILIDAD'],true);

        return view('turnos.mostrar',compact(
            'cabecera','financiero','lecturas','totales','gastos','creditos','columnasGasto','totalesCreditos',
            'efectivoFinal','digitalFinal','esOperador','puedeCorregirEfectivo','puedeValidarDigital'
        ,
            'calculoFinanciero'
        ));
    }


    /**
     * Formulario de edición controlada de un turno.
     *
     * Reglas:
     * - ADMINISTRADOR: cualquier turno, sin límite temporal ni de cantidad.
     * - OPERADOR: únicamente su propio turno, una sola vez y en la misma fecha
     *   de operación (America/Lima).
     */
    public function editar(int $turno)
    {
        $this->autorizar(['ADMINISTRADOR', 'OPERADOR','SUPERVISOR']);

        $turnoObj = $this->obtenerTurnoParaEdicion($turno);
        $this->autorizarModificacionTurno($turnoObj);

        $tipos = DB::table('tipos_turno')->orderBy('id')->get();

        $motivos = Esquema::existeTabla('motivos_turno')
            ? DB::table('motivos_turno')->orderBy('id')->get()
            : collect();

        $esOperador = $this->esOperador();

        if ($esOperador) {
            $trabajadores = DB::table('trabajadores')
                ->where('id', $this->exigirTrabajadorVinculado())
                ->get();
        } else {
            $trabajadores = DB::table('trabajadores')
                ->where('establecimiento_id', $this->establecimientoId())
                ->where(function ($q) use ($turnoObj) {
                    $q->where('activo', 1)
                        ->orWhere('id', $turnoObj->trabajador_id);
                })
                ->orderBy('nombre_completo')
                ->get();
        }

        $mangueras = DB::table('mangueras as m')
            ->join('surtidores as s', 's.id', '=', 'm.surtidor_id')
            ->leftJoin('productos as p', 'p.id', '=', 'm.producto_id')
            ->where('s.establecimiento_id', $this->establecimientoId())
            ->where(function ($q) use ($turnoObj) {
                $q->where('m.activo', 1)
                    ->orWhereExists(function ($sub) use ($turnoObj) {
                        $sub->selectRaw('1')
                            ->from('lecturas_totalizador as le')
                            ->whereColumn('le.manguera_id', 'm.id')
                            ->where('le.turno_operativo_id', $turnoObj->id);
                    });
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

        foreach ($mangueras as $manguera) {
            $actual = DB::table('lecturas_totalizador')
                ->where('turno_operativo_id', $turnoObj->id)
                ->where('manguera_id', $manguera->id)
                ->first();

            $anterior = $this->obtenerLecturaAnteriorTurno(
                (int) $manguera->id,
                $turnoObj->fecha_operacion,
                (int) $turnoObj->id
            );

            $baseReinicio = $this->obtenerBaseReinicioTurnoManguera(
                (int) $turnoObj->id,
                (int) $manguera->id
            );

            if ($baseReinicio !== null) {
                $anterior = (object) [
                    'lectura' => $baseReinicio,
                    'fecha_operacion' => null,
                    'turno_operativo_id' => null,
                ];
            }

            $siguiente = $this->obtenerLecturaSiguienteTurno(
                (int) $manguera->id,
                $turnoObj->fecha_operacion,
                (int) $turnoObj->id
            );

            $siguienteTieneReinicio = $siguiente
                ? $this->obtenerBaseReinicioTurnoManguera(
                    (int) $siguiente->turno_operativo_id,
                    (int) $manguera->id
                ) !== null
                : false;

            $manguera->lectura_actual = $actual?->lectura;
            $manguera->galones_actuales = $actual?->galones_vendidos;
            $manguera->lectura_anterior = $anterior?->lectura;
            $manguera->fecha_lectura_anterior = $anterior?->fecha_operacion;
            $manguera->reinicio_en_turno = $baseReinicio !== null;
            $manguera->lectura_siguiente = $siguiente?->lectura;
            $manguera->fecha_lectura_siguiente = $siguiente?->fecha_operacion;
            $manguera->siguiente_tiene_reinicio = $siguienteTieneReinicio;
        }

        return view('turnos.editar', compact(
            'turnoObj',
            'tipos',
            'motivos',
            'trabajadores',
            'mangueras',
            'esOperador'
        ));
    }

    public function actualizar(Request $request, int $turno)
    {
        $this->autorizar(['ADMINISTRADOR', 'OPERADOR','SUPERVISOR']);

        $request->validate([
            'tipo_turno_id' => ['required', Rule::exists('tipos_turno', 'id')],
            'trabajador_id' => ['nullable', Rule::exists('trabajadores', 'id')],
            'motivo_turno_id' => Esquema::existeTabla('motivos_turno')
                ? ['nullable', Rule::exists('motivos_turno', 'id')]
                : ['nullable'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
            'motivo_edicion' => ['required', 'string', 'max:500'],
            'lecturas' => ['required', 'array'],
            'lecturas.*.lectura' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $turno) {
            $turnoObj = $this->obtenerTurnoParaEdicion($turno, true);
            $this->autorizarModificacionTurno($turnoObj, true);

            if ((int) $request->tipo_turno_id !== (int) $turnoObj->tipo_turno_id) {
                throw ValidationException::withMessages([
                    'tipo_turno_id' =>
                        'El tipo de turno no puede modificarse después de registrarlo porque forma parte de la secuencia operativa.',
                ]);
            }

            $antes = (array) $turnoObj;
            $esOperador = $this->esOperador();

            $trabajadorId = $esOperador
                ? $this->exigirTrabajadorVinculado()
                : ($request->trabajador_id ?: null);

            $datosTurno = [
                'tipo_turno_id' => (int) $request->tipo_turno_id,
                'trabajador_id' => $trabajadorId,
                'motivo_turno_id' => $request->motivo_turno_id ?: null,
                'observaciones' => $request->observaciones ?: null,
                'usuario_ultima_edicion_id' => auth()->id(),
                'actualizado_en' => now('America/Lima'),
            ];

            if ($esOperador) {
                $datosTurno['ediciones_operador'] = (int) ($turnoObj->ediciones_operador ?? 0) + 1;
                $datosTurno['editado_operador_en'] = now('America/Lima');
            }

            DB::table('turnos_operativos')
                ->where('id', $turnoObj->id)
                ->update(Esquema::filtrar('turnos_operativos', $datosTurno));

            $manguerasValidas = DB::table('mangueras as m')
                ->join('surtidores as s', 's.id', '=', 'm.surtidor_id')
                ->where('s.establecimiento_id', $this->establecimientoId())
                ->where(function ($q) use ($turnoObj) {
                    $q->where('m.activo', 1)
                        ->orWhereExists(function ($sub) use ($turnoObj) {
                            $sub->selectRaw('1')
                                ->from('lecturas_totalizador as le')
                                ->whereColumn('le.manguera_id', 'm.id')
                                ->where('le.turno_operativo_id', $turnoObj->id);
                        });
                })
                ->select('m.id', 'm.codigo', 'm.producto_id')
                ->get()
                ->keyBy('id');

            $lecturasFormulario = (array) $request->input('lecturas', []);
            $turnosARecalcular = [];
            $lecturasModificadas = [];

            foreach ($manguerasValidas as $manguera) {
                if (! array_key_exists($manguera->id, $lecturasFormulario)) {
                    continue;
                }

                $fila = (array) $lecturasFormulario[$manguera->id];
                $lecturaFinal = round((float) ($fila['lectura'] ?? 0), 3);

                $actual = DB::table('lecturas_totalizador')
                    ->where('turno_operativo_id', $turnoObj->id)
                    ->where('manguera_id', $manguera->id)
                    ->first();

                $anterior = $this->obtenerLecturaAnteriorTurno(
                    (int) $manguera->id,
                    $turnoObj->fecha_operacion,
                    (int) $turnoObj->id
                );

                $baseReinicio = $this->obtenerBaseReinicioTurnoManguera(
                    (int) $turnoObj->id,
                    (int) $manguera->id
                );

                if ($baseReinicio !== null) {
                    $anterior = (object) [
                        'lectura' => $baseReinicio,
                        'fecha_operacion' => null,
                        'turno_operativo_id' => null,
                    ];
                }

                if (! $anterior) {
                    if ($actual && abs((float) $actual->lectura - $lecturaFinal) < 0.0005) {
                        continue;
                    }

                    throw ValidationException::withMessages([
                        'lecturas.' . $manguera->id . '.lectura' =>
                            'La lectura inicial de la manguera ' . $manguera->codigo .
                            ' no puede modificarse desde la edición de turnos porque no existe una lectura anterior.',
                    ]);
                }

                $lecturaAnterior = round((float) $anterior->lectura, 3);

                if ($lecturaFinal < $lecturaAnterior) {
                    throw ValidationException::withMessages([
                        'lecturas.' . $manguera->id . '.lectura' =>
                            'La lectura final de ' . $manguera->codigo .
                            ' no puede ser menor que su lectura anterior (' .
                            number_format($lecturaAnterior, 3, '.', '') . ').',
                    ]);
                }

                $siguiente = $this->obtenerLecturaSiguienteTurno(
                    (int) $manguera->id,
                    $turnoObj->fecha_operacion,
                    (int) $turnoObj->id
                );

                $siguienteTieneReinicio = $siguiente
                    ? $this->obtenerBaseReinicioTurnoManguera(
                        (int) $siguiente->turno_operativo_id,
                        (int) $manguera->id
                    ) !== null
                    : false;

                if (
                    $siguiente
                    && ! $siguienteTieneReinicio
                    && $lecturaFinal > round((float) $siguiente->lectura, 3)
                ) {
                    throw ValidationException::withMessages([
                        'lecturas.' . $manguera->id . '.lectura' =>
                            'La lectura final de ' . $manguera->codigo .
                            ' no puede superar la lectura del siguiente corte (' .
                            number_format((float) $siguiente->lectura, 3, '.', '') . ').',
                    ]);
                }

                $galones = round($lecturaFinal - $lecturaAnterior, 3);
                $lecturaCambio = ! $actual || abs((float) $actual->lectura - $lecturaFinal) >= 0.0005;
                $galonesCambio = ! $actual || abs((float) ($actual->galones_vendidos ?? 0) - $galones) >= 0.0005;

                if ($actual) {
                    DB::table('lecturas_totalizador')
                        ->where('id', $actual->id)
                        ->update([
                            'lectura' => $lecturaFinal,
                            'galones_vendidos' => $galones,
                        ]);
                } else {
                    DB::table('lecturas_totalizador')->insert([
                        'turno_operativo_id' => $turnoObj->id,
                        'manguera_id' => (int) $manguera->id,
                        'lectura' => $lecturaFinal,
                        'galones_vendidos' => $galones,
                        'hoja_origen' => 'SISTEMA',
                        'creado_en' => now('America/Lima'),
                    ]);
                }

                if ($lecturaCambio || $galonesCambio) {
                    $turnosARecalcular[(int) $turnoObj->id] = true;
                    $lecturasModificadas[] = [
                        'manguera_id' => (int) $manguera->id,
                        'manguera' => $manguera->codigo,
                        'lectura_anterior' => $lecturaAnterior,
                        'lectura_final_antes' => $actual?->lectura,
                        'lectura_final_despues' => $lecturaFinal,
                        'galones_antes' => $actual?->galones_vendidos,
                        'galones_despues' => $galones,
                    ];
                }

                if ($siguiente && ! $siguienteTieneReinicio && $lecturaCambio) {
                    // Cambiar la lectura de un turno también altera el despacho
                    // del siguiente corte. Nunca recalcular un turno validado
                    // por administración mediante esta vía indirecta.
                    PermisosSupervisor::exigirTurnoEditable((int) $siguiente->turno_operativo_id, true);
                    $galonesSiguiente = round((float) $siguiente->lectura - $lecturaFinal, 3);

                    if ($galonesSiguiente < 0) {
                        throw ValidationException::withMessages([
                            'lecturas.' . $manguera->id . '.lectura' =>
                                'La modificación produciría galones negativos en el siguiente corte de ' .
                                $manguera->codigo . '.',
                        ]);
                    }

                    DB::table('lecturas_totalizador')
                        ->where('id', $siguiente->lectura_id)
                        ->update(['galones_vendidos' => $galonesSiguiente]);

                    $turnosARecalcular[(int) $siguiente->turno_operativo_id] = true;
                }
            }

            foreach (array_keys($turnosARecalcular) as $turnoRecalcular) {
                $this->recalcularResumenTurnoEditado((int) $turnoRecalcular);
            }

            $despues = (array) DB::table('turnos_operativos')
                ->where('id', $turnoObj->id)
                ->first();

            $despues['motivo_edicion'] = $request->motivo_edicion;
            $despues['lecturas_modificadas'] = $lecturasModificadas;

            ServicioAuditoria::registrar(
                'TURNO_OPERATIVO',
                $turnoObj->id,
                'ACTUALIZAR',
                $antes,
                $despues,
                $request
            );
        });

        return redirect()
            ->route('turnos.mostrar', $turno)
            ->with('exito', 'Turno actualizado correctamente.');
    }

    private function obtenerTurnoParaEdicion(int $turno, bool $bloquear = false): object
    {
        $consulta = DB::table('turnos_operativos')
            ->where('id', $turno)
            ->where('establecimiento_id', $this->establecimientoId());

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        $turnoObj = $consulta->first();
        abort_unless($turnoObj, 404);

        return $turnoObj;
    }

    private function autorizarModificacionTurno(object $turno, bool $bloquear = false): void
    {
        $rol = (string) auth()->user()->rol;

        if ($rol === 'ADMINISTRADOR') {
            return;
        }

        if ($rol === 'SUPERVISOR') {
            PermisosSupervisor::exigirTurnoEditable((int) $turno->id, $bloquear);
            return;
        }

        abort_unless($rol === 'OPERADOR', 403);

        $trabajadorId = $this->exigirTrabajadorVinculado();

        abort_unless(
            (int) $turno->trabajador_id === (int) $trabajadorId,
            403,
            'Solo puedes modificar tus propios turnos.'
        );

        abort_if(
            (string) $turno->fecha_operacion !== now('America/Lima')->toDateString(),
            403,
            'Este turno ya no puede modificarse porque el día operativo terminó.'
        );

        abort_if(
            (int) ($turno->ediciones_operador ?? 0) >= 1,
            403,
            'Este turno ya fue modificado una vez por el operador.'
        );
    }

    private function obtenerLecturaAnteriorTurno(
        int $mangueraId,
        string $fechaOperacion,
        int $turnoId
    ): ?object {
        return DB::table('lecturas_totalizador as l')
            ->join('turnos_operativos as t', 't.id', '=', 'l.turno_operativo_id')
            ->where('t.establecimiento_id', $this->establecimientoId())
            ->where('l.manguera_id', $mangueraId)
            ->where(function ($q) use ($fechaOperacion, $turnoId) {
                $q->where('t.fecha_operacion', '<', $fechaOperacion)
                    ->orWhere(function ($mismaFecha) use ($fechaOperacion, $turnoId) {
                        $mismaFecha
                            ->where('t.fecha_operacion', '=', $fechaOperacion)
                            ->where('t.id', '<', $turnoId);
                    });
            })
            ->orderByDesc('t.fecha_operacion')
            ->orderByDesc('t.id')
            ->orderByDesc('l.id')
            ->select(
                'l.id as lectura_id',
                'l.lectura',
                'l.galones_vendidos',
                'l.turno_operativo_id',
                't.fecha_operacion'
            )
            ->first();
    }

    private function obtenerLecturaSiguienteTurno(
        int $mangueraId,
        string $fechaOperacion,
        int $turnoId
    ): ?object {
        return DB::table('lecturas_totalizador as l')
            ->join('turnos_operativos as t', 't.id', '=', 'l.turno_operativo_id')
            ->where('t.establecimiento_id', $this->establecimientoId())
            ->where('l.manguera_id', $mangueraId)
            ->where(function ($q) use ($fechaOperacion, $turnoId) {
                $q->where('t.fecha_operacion', '>', $fechaOperacion)
                    ->orWhere(function ($mismaFecha) use ($fechaOperacion, $turnoId) {
                        $mismaFecha
                            ->where('t.fecha_operacion', '=', $fechaOperacion)
                            ->where('t.id', '>', $turnoId);
                    });
            })
            ->orderBy('t.fecha_operacion')
            ->orderBy('t.id')
            ->orderBy('l.id')
            ->select(
                'l.id as lectura_id',
                'l.lectura',
                'l.galones_vendidos',
                'l.turno_operativo_id',
                't.fecha_operacion'
            )
            ->first();
    }

    private function obtenerBaseReinicioTurnoManguera(
        int $turnoId,
        int $mangueraId
    ): ?float {
        if (
            ! Esquema::existeTabla('reinicios_totalizador')
            || ! Esquema::existeTabla('reinicios_totalizador_detalle')
        ) {
            return null;
        }

        $base = DB::table('reinicios_totalizador as r')
            ->join(
                'reinicios_totalizador_detalle as d',
                'd.reinicio_totalizador_id',
                '=',
                'r.id'
            )
            ->where('r.establecimiento_id', $this->establecimientoId())
            ->where('r.turno_operativo_id', $turnoId)
            ->where('r.estado', 'APLICADO')
            ->where('d.manguera_id', $mangueraId)
            ->value('d.lectura_base');

        return $base === null ? null : round((float) $base, 3);
    }

    private function recalcularResumenTurnoEditado(int $turnoId): void
    {
        // [PARCHE_FINANCIERO_TURNOS] una sola fuente de verdad para el cálculo financiero.
        app(\App\Services\ServicioFinancieroTurno::class)->sincronizar($turnoId);
    }
    public function registrarEntrega(Request $request, int $turno)
    {
        $this->autorizar(['OPERADOR','SUPERVISOR']);
        $this->turnoPropio($turno);
        $datosEntrada = $request->validate([
            'efectivo_entregado' => ['required','numeric','min:0'],
            'digital_operador' => ['required','numeric','min:0'],
        ]);

        DB::transaction(function () use ($request, $turno, $datosEntrada) {
            PermisosSupervisor::exigirTurnoEditable($turno, true);

            // [PARCHE_FINANCIERO_TURNOS] el monto esperado se recalcula antes de conciliar.
            app(\App\Services\ServicioFinancieroTurno::class)->sincronizar($turno);

            $antes = DB::table('resumen_financiero_turno')
                ->where('turno_operativo_id', $turno)
                ->first();
            abort_unless($antes, 404);

            $datos = [
                'efectivo_entregado' => round((float) $datosEntrada['efectivo_entregado'], 2),
                'digital_operador' => round((float) $datosEntrada['digital_operador'], 2),
            ];

            if (Esquema::tiene('resumen_financiero_turno','digital_entregado')) {
                $datos['digital_entregado'] = $datos['digital_operador'];
            }

            // Una nueva entrega del operador invalida validaciones administrativas anteriores.
            if (Esquema::tiene('resumen_financiero_turno','digital_administrador')) $datos['digital_administrador'] = null;
            if (Esquema::tiene('resumen_financiero_turno','estado_validacion_digital')) $datos['estado_validacion_digital'] = 'PENDIENTE';
            if (Esquema::tiene('resumen_financiero_turno','usuario_validador_digital_id')) $datos['usuario_validador_digital_id'] = null;
            if (Esquema::tiene('resumen_financiero_turno','validado_digital_en')) $datos['validado_digital_en'] = null;
            if (Esquema::tiene('resumen_financiero_turno','observacion_validacion_digital')) $datos['observacion_validacion_digital'] = null;
            if (Esquema::tiene('resumen_financiero_turno','efectivo_administrador')) $datos['efectivo_administrador'] = null;
            if (Esquema::tiene('resumen_financiero_turno','usuario_validador_efectivo_id')) $datos['usuario_validador_efectivo_id'] = null;
            if (Esquema::tiene('resumen_financiero_turno','validado_efectivo_en')) $datos['validado_efectivo_en'] = null;
            if (Esquema::tiene('resumen_financiero_turno','observacion_validacion_efectivo')) $datos['observacion_validacion_efectivo'] = null;

            $datos = Esquema::filtrar('resumen_financiero_turno', $datos);
            DB::table('resumen_financiero_turno')->where('turno_operativo_id', $turno)->update($datos);

            if (class_exists(\App\Services\ServicioAuditoria::class)) {
                \App\Services\ServicioAuditoria::registrar('ENTREGA_TURNO', $turno, 'ACTUALIZAR', (array) $antes, $datos, $request);
            }
        }, 3);

        return redirect()->route('turnos.mostrar', $turno)
            ->with('exito', 'Entrega registrada. La diferencia fue recalculada contra el monto a entregar.');
    }

    public function corregirEfectivo(Request $request, int $turno)
    {
        $this->autorizar(['ADMINISTRADOR']);
        $this->turnoPropio($turno);

        $request->validate([
            'efectivo_administrador'=>['required','numeric','min:0','max:9999999.99'],
            'observacion_validacion_efectivo'=>['nullable','string','max:255'],
        ]);

        DB::transaction(function () use ($request, $turno) {
            $financiero = DB::table('resumen_financiero_turno')->where('turno_operativo_id', $turno)->lockForUpdate()->first();
            abort_unless($financiero,404);

            $antes = (array)$financiero;
            $efectivo = round((float)$request->efectivo_administrador,2);
            $digital = (float)($financiero->digital_administrador ?? $financiero->digital_operador ?? $financiero->digital_entregado ?? 0);
            $neto = (float)($financiero->neto_esperado ?? 0);

            $datos = [];
            if (Esquema::tiene('resumen_financiero_turno','efectivo_administrador')) {
                $datos['efectivo_administrador'] = $efectivo;
                $datos['usuario_validador_efectivo_id'] = auth()->id();
                $datos['validado_efectivo_en'] = now();
                $datos['observacion_validacion_efectivo'] = $request->observacion_validacion_efectivo ?: null;
            } else {
                // Compatibilidad temporal si todavía no se ejecutó la migración incluida en este parche.
                $datos['efectivo_entregado'] = $efectivo;
            }

            $datos['diferencia_caja'] = round($efectivo + $digital - $neto,2);
            $datos = Esquema::filtrar('resumen_financiero_turno',$datos);

            DB::table('resumen_financiero_turno')->where('turno_operativo_id',$turno)->update($datos);

            ServicioAuditoria::registrar('VALIDACION_EFECTIVO',$turno,'ACTUALIZAR',$antes,$datos,$request);
        }, 3);

        return back()->with('exito','Efectivo del turno corregido por administración.');
    }

    public function validarDigital(Request $request, int $turno)
    {
        // PERMISOS_SUPERVISOR_NO_VALIDAR
        if (PermisosSupervisor::esSupervisor()) {
            abort(403, 'La validación administrativa del turno no corresponde al rol Supervisor.');
        }

        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD']);

        $request->validate([
            'digital_administrador'=>['required','numeric','min:0'],
            'estado_validacion_digital'=>['required',Rule::in(['VALIDADO','OBSERVADO'])],
            'observacion_validacion_digital'=>['nullable','string','max:255'],
        ]);

        $this->turnoPropio($turno);

        DB::transaction(function () use ($request, $turno) {
            $financiero = DB::table('resumen_financiero_turno')->where('turno_operativo_id', $turno)->lockForUpdate()->first();
            abort_unless($financiero,404);

            $antes = (array)$financiero;
            $digital = round((float)$request->digital_administrador,2);
            $efectivo = (float)($financiero->efectivo_entregado ?? 0);
            if (Esquema::tiene('resumen_financiero_turno','efectivo_administrador') && $financiero->efectivo_administrador !== null) {
                $efectivo = (float)$financiero->efectivo_administrador;
            }
            $neto = (float)($financiero->neto_esperado ?? 0);

            $datos = Esquema::filtrar('resumen_financiero_turno',[
                'digital_administrador'=>$digital,
                'estado_validacion_digital'=>$request->estado_validacion_digital,
                'observacion_validacion_digital'=>$request->observacion_validacion_digital,
                'usuario_validador_digital_id'=>auth()->id(),
                'validado_digital_en'=>now(),
                'diferencia_caja'=>round($efectivo + $digital - $neto,2),
            ]);

            DB::table('resumen_financiero_turno')->where('turno_operativo_id',$turno)->update($datos);
            ServicioAuditoria::registrar('VALIDACION_DIGITAL',$turno,'ACTUALIZAR',$antes,$datos,$request);
        }, 3);

        return back()->with('exito','Validación digital actualizada.');
    }
}
