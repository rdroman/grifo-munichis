<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ControladorAjustesPersonal extends ControladorBaseGrifo
{
    private string $tabla = 'ajustes_mensuales_trabajador';

    public function indice(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD', 'LECTURA']);

        $columnas = $this->columnasAjuste();
        $this->validarEstructura($columnas);

        $buscar = trim((string) $request->query('buscar', ''));
        $anio = trim((string) $request->query('anio', ''));
        $mesNumero = trim((string) $request->query('mes', ''));
        $estadoFiltro = trim((string) $request->query('estado', ''));

        $consulta = DB::table($this->tabla . ' as a')
            ->join('trabajadores as tr', 'tr.id', '=', 'a.' . $columnas['trabajador'])
            ->where('tr.establecimiento_id', $this->establecimientoId())
            ->select('a.*', 'tr.nombre_completo as trabajador');

        if ($buscar !== '') {
            $consulta->where('tr.nombre_completo', 'like', '%' . $buscar . '%');
        }

        if ($anio !== '' && ctype_digit($anio)) {
            $consulta->whereYear('a.' . $columnas['mes'], (int) $anio);
        }

        if ($mesNumero !== '' && ctype_digit($mesNumero)) {
            $consulta->whereMonth('a.' . $columnas['mes'], (int) $mesNumero);
        }

        // PAGADO sí puede filtrarse directamente. PENDIENTE/LISTO se derivan
        // con las validaciones actuales de los turnos, por lo que se filtran
        // después de calcular cada registro.
        if ($estadoFiltro === 'PAGADO' && Schema::hasColumn($this->tabla, 'estado_pago')) {
            $consulta->where('a.estado_pago', 'PAGADO');
        }

        $registros = $consulta
            ->orderByDesc('a.' . $columnas['mes'])
            ->orderBy('tr.nombre_completo')
            ->paginate(20)
            ->withQueryString();

        $registros->through(function ($registro) use ($columnas) {
            $trabajadorId = (int) data_get($registro, $columnas['trabajador']);
            $mes = $this->normalizarMes((string) data_get($registro, $columnas['mes']));
            $automatico = $this->calcularAutomaticos($trabajadorId, $mes);

            $sueldoNeto = round((float) (data_get($registro, $columnas['sueldo']) ?? 0), 2);
            $saldoSueldo = $this->saldoSueldo($sueldoNeto, $automatico);

            $estadoGuardado = strtoupper((string) data_get($registro, 'estado_pago', 'PENDIENTE'));

            if ($estadoGuardado === 'PAGADO') {
                $estado = 'PAGADO';
            } elseif ($automatico['turnos_pendientes'] > 0) {
                $estado = 'PENDIENTE';
            } else {
                $estado = 'LISTO_PARA_PAGAR';
            }

            $registro->mes_normalizado = $mes;
            $registro->anio_calculado = Carbon::parse($mes)->year;
            $registro->mes_nombre = Carbon::parse($mes)->locale('es')->translatedFormat('F');

            $registro->adelantos_calculados = $automatico['adelantos'];
            $registro->faltantes_calculados = $automatico['faltantes'];
            $registro->sobrantes_calculados = $automatico['sobrantes'];

            $registro->sueldo_neto_calculado = $sueldoNeto;
            $registro->saldo_sueldo_calculado = $saldoSueldo;

            $registro->turnos_validados = $automatico['turnos_validados'];
            $registro->turnos_pendientes = $automatico['turnos_pendientes'];
            $registro->estado_calculado = $estado;

            return $registro;
        });

        // Filtro derivado sobre la colección de la página actual.
        // Evita falsear el estado usando valores históricos almacenados.
        if (in_array($estadoFiltro, ['PENDIENTE', 'LISTO_PARA_PAGAR'], true)) {
            $registros->setCollection(
                $registros->getCollection()
                    ->filter(fn ($r) => $r->estado_calculado === $estadoFiltro)
                    ->values()
            );
        }

        $puedeEditar = in_array(
            auth()->user()->rol,
            ['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD'],
            true
        );

        $puedeMarcarPago = in_array(
            auth()->user()->rol,
            ['ADMINISTRADOR', 'GERENCIA', 'CONTABILIDAD'],
            true
        );

        $aniosDisponibles = DB::table($this->tabla)
            ->selectRaw('YEAR(' . $columnas['mes'] . ') as anio')
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio');

        return view('ajustes_personal.indice', compact(
            'registros',
            'buscar',
            'anio',
            'mesNumero',
            'estadoFiltro',
            'puedeEditar',
            'puedeMarcarPago',
            'aniosDisponibles'
        ));
    }

    public function crear()
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD']);

        return view('ajustes_personal.formulario', [
            'trabajadores' => $this->trabajadores(),
            'ajuste' => null,
            'esEdicion' => false,
            'valores' => [
                'trabajador_id' => null,
                'mes' => now('America/Lima')->startOfMonth()->toDateString(),
                'sueldo_neto' => null,
            ],
            'automatico' => $this->automaticoVacio(),
            'estadoActual' => 'PENDIENTE',
        ]);
    }

    public function editar(int $ajuste)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD']);

        $columnas = $this->columnasAjuste();
        $this->validarEstructura($columnas);

        $registro = $this->obtenerAjuste($ajuste, $columnas);

        if (strtoupper((string) data_get($registro, 'estado_pago', '')) === 'PAGADO') {
            throw ValidationException::withMessages([
                'estado' => 'Este pago ya fue marcado como PAGADO. Reábrelo antes de modificarlo.',
            ]);
        }

        $trabajadorId = (int) data_get($registro, $columnas['trabajador']);
        $mes = $this->normalizarMes((string) data_get($registro, $columnas['mes']));
        $automatico = $this->calcularAutomaticos($trabajadorId, $mes);

        return view('ajustes_personal.formulario', [
            'trabajadores' => $this->trabajadores(),
            'ajuste' => $registro,
            'esEdicion' => true,
            'valores' => [
                'trabajador_id' => $trabajadorId,
                'mes' => $mes,
                'sueldo_neto' => data_get($registro, $columnas['sueldo']),
            ],
            'automatico' => $automatico,
            'estadoActual' => $automatico['turnos_pendientes'] > 0
                ? 'PENDIENTE'
                : 'LISTO_PARA_PAGAR',
        ]);
    }

    public function resumen(Request $request): JsonResponse
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD', 'LECTURA']);

        $datos = $request->validate([
            'trabajador_id' => ['required', 'integer'],
            'mes' => ['required', 'regex:/^\d{4}-\d{2}(?:-\d{2})?$/'],
        ]);

        $trabajadorId = (int) $datos['trabajador_id'];
        $this->exigirTrabajador($trabajadorId);

        $mes = $this->normalizarMes($datos['mes']);
        $automatico = $this->calcularAutomaticos($trabajadorId, $mes);

        return response()->json([
            'ok' => true,
            'mes' => $mes,
            'estado' => $automatico['turnos_pendientes'] > 0
                ? 'PENDIENTE'
                : 'LISTO_PARA_PAGAR',
            ...$automatico,
        ]);
    }

    public function guardar(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD']);

        $datos = $this->validarFormulario($request);
        $columnas = $this->columnasAjuste();
        $this->validarEstructura($columnas);

        $trabajadorId = (int) $datos['trabajador_id'];
        $this->exigirTrabajador($trabajadorId);

        $mes = $this->normalizarMes($datos['mes']);
        $this->validarDuplicado($trabajadorId, $mes, null, $columnas);

        $automatico = $this->calcularAutomaticos($trabajadorId, $mes);
        $sueldoNeto = round((float) $datos['sueldo_neto'], 2);
        $saldoSueldo = $this->saldoSueldo($sueldoNeto, $automatico);

        $estado = $automatico['turnos_pendientes'] > 0
            ? 'PENDIENTE'
            : 'LISTO_PARA_PAGAR';

        $guardar = $this->datosPersistencia(
            $columnas,
            $trabajadorId,
            $mes,
            $sueldoNeto,
            $saldoSueldo,
            $automatico,
            $estado,
            true
        );

        $id = DB::table($this->tabla)->insertGetId($guardar);

        $this->auditar('CREAR', $id, null, $guardar, $request);

        return redirect()
            ->route('ajustes-personal.indice')
            ->with('exito', 'Pago mensual calculado correctamente.');
    }

    public function actualizar(Request $request, int $ajuste)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD']);

        $columnas = $this->columnasAjuste();
        $this->validarEstructura($columnas);

        $actual = $this->obtenerAjuste($ajuste, $columnas);

        if (strtoupper((string) data_get($actual, 'estado_pago', '')) === 'PAGADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede modificar un pago ya marcado como PAGADO. Reábrelo primero.',
            ]);
        }

        $datos = $this->validarFormulario($request);

        $trabajadorId = (int) $datos['trabajador_id'];
        $this->exigirTrabajador($trabajadorId);

        $mes = $this->normalizarMes($datos['mes']);
        $this->validarDuplicado($trabajadorId, $mes, $ajuste, $columnas);

        $automatico = $this->calcularAutomaticos($trabajadorId, $mes);
        $sueldoNeto = round((float) $datos['sueldo_neto'], 2);
        $saldoSueldo = $this->saldoSueldo($sueldoNeto, $automatico);

        $estado = $automatico['turnos_pendientes'] > 0
            ? 'PENDIENTE'
            : 'LISTO_PARA_PAGAR';

        $guardar = $this->datosPersistencia(
            $columnas,
            $trabajadorId,
            $mes,
            $sueldoNeto,
            $saldoSueldo,
            $automatico,
            $estado,
            false
        );

        DB::table($this->tabla)->where('id', $ajuste)->update($guardar);

        $this->auditar('ACTUALIZAR', $ajuste, (array) $actual, $guardar, $request);

        return redirect()
            ->route('ajustes-personal.indice')
            ->with('exito', 'Pago mensual actualizado correctamente.');
    }

    public function marcarPagado(Request $request, int $ajuste)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'CONTABILIDAD']);

        $columnas = $this->columnasAjuste();
        $this->validarEstructura($columnas);

        $actual = $this->obtenerAjuste($ajuste, $columnas);

        $trabajadorId = (int) data_get($actual, $columnas['trabajador']);
        $mes = $this->normalizarMes((string) data_get($actual, $columnas['mes']));
        $automatico = $this->calcularAutomaticos($trabajadorId, $mes);

        if ($automatico['turnos_pendientes'] > 0) {
            throw ValidationException::withMessages([
                'estado' => 'No puede marcarse como PAGADO mientras existan turnos pendientes de validación administrativa.',
            ]);
        }

        $cambios = ['estado_pago' => 'PAGADO'];

        if (Schema::hasColumn($this->tabla, 'pagado_en')) {
            $cambios['pagado_en'] = now();
        }

        if (Schema::hasColumn($this->tabla, 'actualizado_en')) {
            $cambios['actualizado_en'] = now();
        }

        DB::table($this->tabla)->where('id', $ajuste)->update($cambios);

        $this->auditar('MARCAR_PAGADO', $ajuste, (array) $actual, $cambios, $request);

        return back()->with('exito', 'El sueldo fue marcado como pagado.');
    }

    public function reabrir(Request $request, int $ajuste)
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'CONTABILIDAD']);

        $columnas = $this->columnasAjuste();
        $this->validarEstructura($columnas);

        $actual = $this->obtenerAjuste($ajuste, $columnas);

        $trabajadorId = (int) data_get($actual, $columnas['trabajador']);
        $mes = $this->normalizarMes((string) data_get($actual, $columnas['mes']));
        $automatico = $this->calcularAutomaticos($trabajadorId, $mes);

        $estado = $automatico['turnos_pendientes'] > 0
            ? 'PENDIENTE'
            : 'LISTO_PARA_PAGAR';

        $cambios = ['estado_pago' => $estado];

        if (Schema::hasColumn($this->tabla, 'pagado_en')) {
            $cambios['pagado_en'] = null;
        }

        if (Schema::hasColumn($this->tabla, 'actualizado_en')) {
            $cambios['actualizado_en'] = now();
        }

        DB::table($this->tabla)->where('id', $ajuste)->update($cambios);

        $this->auditar('REABRIR_PAGO', $ajuste, (array) $actual, $cambios, $request);

        return back()->with('exito', 'El pago mensual fue reabierto.');
    }

    private function calcularAutomaticos(int $trabajadorId, string $mes): array
    {
        $inicio = Carbon::parse($mes)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $adelantos = $this->calcularAdelantos(
            $trabajadorId,
            $inicio->toDateString(),
            $fin->toDateString()
        );

        $diferencias = $this->calcularDiferenciasValidadas(
            $trabajadorId,
            $inicio->toDateString(),
            $fin->toDateString()
        );

        return [
            'adelantos' => round($adelantos, 2),
            'faltantes' => round($diferencias['faltantes'], 2),
            'sobrantes' => round($diferencias['sobrantes'], 2),
            'diferencia' => round($diferencias['sobrantes'] - $diferencias['faltantes'], 2),
            'turnos_validados' => $diferencias['turnos_validados'],
            'turnos_pendientes' => $diferencias['turnos_pendientes'],
        ];
    }

    private function calcularAdelantos(int $trabajadorId, string $inicio, string $fin): float
    {
        if (! Schema::hasTable('gastos')) {
            return 0.0;
        }

        $fecha = $this->resolverColumna('gastos', ['fecha_gasto', 'fecha']);
        $monto = $this->resolverColumna('gastos', ['monto', 'importe']);
        $trabajador = $this->resolverColumna('gastos', ['trabajador_id']);
        $establecimiento = $this->resolverColumna('gastos', ['establecimiento_id']);
        $categoriaId = $this->resolverColumna('gastos', ['categoria_id']);
        $detalle = $this->resolverColumna('gastos', ['detalle']);

        if (! $fecha || ! $monto || ! $trabajador) {
            return 0.0;
        }

        $consulta = DB::table('gastos as g')
            ->where('g.' . $trabajador, $trabajadorId)
            ->whereBetween('g.' . $fecha, [$inicio, $fin]);

        if ($establecimiento) {
            $consulta->where('g.' . $establecimiento, $this->establecimientoId());
        }

        $puedeUsarCategoria = $categoriaId
            && Schema::hasTable('categorias_gasto')
            && ($nombreCategoria = $this->resolverColumna('categorias_gasto', ['nombre', 'descripcion']));

        if ($puedeUsarCategoria) {
            $consulta->leftJoin('categorias_gasto as cg', 'cg.id', '=', 'g.' . $categoriaId);

            $consulta->where(function ($sub) use ($nombreCategoria, $detalle) {
                $sub->whereRaw('LOWER(cg.' . $nombreCategoria . ") LIKE ?", ['%adelant%']);

                if ($detalle) {
                    $sub->orWhereRaw('LOWER(g.' . $detalle . ") LIKE ?", ['%adelant%']);
                }
            });
        } elseif ($detalle) {
            $consulta->whereRaw('LOWER(g.' . $detalle . ") LIKE ?", ['%adelant%']);
        } else {
            return 0.0;
        }

        return (float) $consulta->sum('g.' . $monto);
    }

    private function calcularDiferenciasValidadas(
    int $trabajadorId,
    string $inicio,
    string $fin
): array {
    if (
        ! Schema::hasTable('turnos_operativos')
        || ! Schema::hasTable('resumen_financiero_turno')
    ) {
        return [
            'faltantes' => 0.0,
            'sobrantes' => 0.0,
            'turnos_validados' => 0,
            'turnos_pendientes' => 0,
        ];
    }

    $filas = DB::table('turnos_operativos as t')
        ->leftJoin(
            'resumen_financiero_turno as rf',
            'rf.turno_operativo_id',
            '=',
            't.id'
        )
        ->where('t.establecimiento_id', $this->establecimientoId())
        ->where('t.trabajador_id', $trabajadorId)
        ->whereBetween('t.fecha_operacion', [$inicio, $fin])
        ->get([
            't.id',

            // Solo fallback para registros históricos anómalos.
            'rf.neto_esperado',

            'rf.efectivo_entregado',
            'rf.digital_operador',

            'rf.efectivo_administrador',
            'rf.digital_administrador',

            'rf.validado_efectivo_en',
            'rf.estado_validacion_digital',
        ]);

    $faltantes = 0.0;
    $sobrantes = 0.0;
    $validados = 0;
    $pendientes = 0;

    /*
     * Misma fuente financiera utilizada por Turnos
     * y por Sobrantes/Faltantes.
     */
    $servicioFinanciero = app(
        \App\Services\ServicioFinancieroTurno::class
    );

    foreach ($filas as $fila) {

        /*
         * ---------------------------------------------------------
         * 1. Mismo criterio de validación que ControladorTurnos
         * ---------------------------------------------------------
         */
        $turnoValidado =
            ($fila->validado_efectivo_en ?? null) !== null
            && ($fila->estado_validacion_digital ?? null) === 'VALIDADO';

        if (! $turnoValidado) {
            $pendientes++;
            continue;
        }

        /*
         * ---------------------------------------------------------
         * 2. Recalcular monto esperado con la fuente oficial
         * ---------------------------------------------------------
         */
        try {
            $calculo = $servicioFinanciero->calcular((int) $fila->id);

            $netoEsperado = round(
                (float) ($calculo['monto_a_entregar'] ?? 0),
                2
            );
        } catch (\Throwable $e) {

            /*
             * Fallback defensivo para datos históricos.
             */
            if ($fila->neto_esperado === null) {
                $pendientes++;
                continue;
            }

            $netoEsperado = round(
                (float) $fila->neto_esperado,
                2
            );
        }

        /*
         * ---------------------------------------------------------
         * 3. Misma conciliación utilizada por ControladorTurnos
         * ---------------------------------------------------------
         */

        $efectivoOperadorOriginal =
            $fila->efectivo_entregado ?? null;

        $digitalOperadorOriginal =
            $fila->digital_operador ?? null;

        $efectivoAdmin =
            $fila->efectivo_administrador ?? null;

        $digitalAdmin =
            $fila->digital_administrador ?? null;

        /*
         * Compatibilidad histórica:
         * si operador tiene NULL/0 pero existe dato administrativo,
         * utilizar el administrativo como fallback.
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

        $montoBase = (
            $turnoValidado
            && $montoAdmin !== null
        )
            ? $montoAdmin
            : $montoOperador;

        /*
         * ---------------------------------------------------------
         * 4. Diferencia definitiva
         * ---------------------------------------------------------
         */
        $diferencia = round(
            $montoBase - $netoEsperado,
            2
        );

        $validados++;

        if ($diferencia < 0) {
            $faltantes += abs($diferencia);
        } elseif ($diferencia > 0) {
            $sobrantes += $diferencia;
        }
    }

    return [
        'faltantes' => round($faltantes, 2),
        'sobrantes' => round($sobrantes, 2),
        'turnos_validados' => $validados,
        'turnos_pendientes' => $pendientes,
    ];
}

    private function saldoSueldo(float $sueldoNeto, array $automatico): float
    {
        return round(
            $sueldoNeto
            - (float) $automatico['adelantos']
            - (float) $automatico['faltantes']
            + (float) $automatico['sobrantes'],
            2
        );
    }

    private function datosPersistencia(
        array $columnas,
        int $trabajadorId,
        string $mes,
        float $sueldoNeto,
        float $saldoSueldo,
        array $automatico,
        string $estado,
        bool $crear
    ): array {
        $datos = [
            $columnas['trabajador'] => $trabajadorId,
            $columnas['mes'] => $mes,
            $columnas['adelantos'] => $automatico['adelantos'],
            $columnas['diferencia'] => $automatico['diferencia'],
            $columnas['sueldo'] => $sueldoNeto,
            $columnas['saldo'] => $saldoSueldo,
        ];

        if (Schema::hasColumn($this->tabla, 'estado_pago')) {
            $datos['estado_pago'] = $estado;
        }

        if ($crear && Schema::hasColumn($this->tabla, 'creado_en')) {
            $datos['creado_en'] = now();
        }

        if (Schema::hasColumn($this->tabla, 'actualizado_en')) {
            $datos['actualizado_en'] = now();
        }

        return $datos;
    }

    private function automaticoVacio(): array
    {
        return [
            'adelantos' => 0.0,
            'faltantes' => 0.0,
            'sobrantes' => 0.0,
            'diferencia' => 0.0,
            'turnos_validados' => 0,
            'turnos_pendientes' => 0,
        ];
    }

    private function columnasAjuste(): array
    {
        return [
            'trabajador' => $this->resolverColumna($this->tabla, ['trabajador_id', 'empleado_id']),
            'mes' => $this->resolverColumna($this->tabla, ['mes', 'mes_inicio', 'fecha_mes', 'periodo']),
            'diferencia' => $this->resolverColumna($this->tabla, ['diferencia_caja', 'diferencia', 'diferencia_mensual', 'ajuste_diferencias']),
            'adelantos' => $this->resolverColumna($this->tabla, ['adelantos', 'adelanto']),
            'sueldo' => $this->resolverColumna($this->tabla, ['sueldo_neto', 'sueldo_base', 'sueldo', 'salario_base']),
            'saldo' => $this->resolverColumna($this->tabla, ['saldo_sueldo', 'monto_neto', 'neto', 'importe_neto']),
        ];
    }

    private function validarEstructura(array $columnas): void
    {
        if (! Schema::hasTable($this->tabla)) {
            abort(500, 'No existe la tabla ajustes_mensuales_trabajador.');
        }

        foreach (['trabajador', 'mes', 'diferencia', 'adelantos', 'sueldo', 'saldo'] as $clave) {
            if (! $columnas[$clave]) {
                abort(500, 'Falta una columna compatible para: ' . $clave . '.');
            }
        }
    }

    private function resolverColumna(string $tabla, array $candidatos): ?string
    {
        if (! Schema::hasTable($tabla)) {
            return null;
        }

        foreach ($candidatos as $columna) {
            if (Schema::hasColumn($tabla, $columna)) {
                return $columna;
            }
        }

        return null;
    }

    private function validarFormulario(Request $request): array
    {
        return $request->validate([
            'trabajador_id' => ['required', 'integer'],
            'mes' => ['required', 'regex:/^\d{4}-\d{2}(?:-\d{2})?$/'],
            'sueldo_neto' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
    }

    private function normalizarMes(string $valor): string
    {
        try {
            if (preg_match('/^\d{4}-\d{2}$/', $valor)) {
                $valor .= '-01';
            }

            return Carbon::parse($valor)->startOfMonth()->toDateString();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'mes' => 'El mes seleccionado no es válido.',
            ]);
        }
    }

    private function trabajadores()
    {
        return DB::table('trabajadores')
            ->where('establecimiento_id', $this->establecimientoId())
            ->orderByDesc('activo')
            ->orderBy('nombre_completo')
            ->get(['id', 'nombre_completo', 'activo']);
    }

    private function exigirTrabajador(int $trabajadorId): void
    {
        $existe = DB::table('trabajadores')
            ->where('id', $trabajadorId)
            ->where('establecimiento_id', $this->establecimientoId())
            ->exists();

        abort_unless($existe, 404);
    }

    private function obtenerAjuste(int $id, array $columnas): object
    {
        $registro = DB::table($this->tabla . ' as a')
            ->join('trabajadores as tr', 'tr.id', '=', 'a.' . $columnas['trabajador'])
            ->where('a.id', $id)
            ->where('tr.establecimiento_id', $this->establecimientoId())
            ->select('a.*')
            ->first();

        abort_unless($registro, 404);

        return $registro;
    }

    private function validarDuplicado(int $trabajadorId, string $mes, ?int $ignorarId, array $columnas): void
    {
        $consulta = DB::table($this->tabla)
            ->where($columnas['trabajador'], $trabajadorId)
            ->where($columnas['mes'], $mes);

        if ($ignorarId !== null) {
            $consulta->where('id', '<>', $ignorarId);
        }

        if ($consulta->exists()) {
            throw ValidationException::withMessages([
                'mes' => 'Ya existe un pago mensual para este trabajador y mes.',
            ]);
        }
    }

    private function auditar(string $accion, int $id, ?array $antes, array $despues, Request $request): void
    {
        if (! Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        try {
            DB::table('bitacora_auditoria')->insert([
                'usuario_id' => auth()->id(),
                'tipo_entidad' => 'PAGO_MENSUAL_PERSONAL',
                'entidad_id' => (string) $id,
                'accion' => $accion,
                'antes_json' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
                'despues_json' => json_encode($despues, JSON_UNESCAPED_UNICODE),
                'direccion_ip' => $request->ip(),
                'creado_en' => now(),
            ]);
        } catch (\Throwable $e) {
            // La auditoría no debe impedir la operación principal.
        }
    }
}
