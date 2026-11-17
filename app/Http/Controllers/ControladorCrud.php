<?php

namespace App\Http\Controllers;



use App\Support\PermisosSupervisor;
use App\Support\AccesoGastosSupervisor;
use App\Services\ServicioAuditoria;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ControladorCrud extends ControladorBaseGrifo
{
    public function indice(Request $request, string $modulo)
    {
        // El descuento se registra mediante su controlador especializado,
        // que aplica el precio vigente, sincroniza el turno y audita la operación.
        if ($modulo === 'descuentos') {
            return redirect()->route('descuentos.indice');
        }
        [$config, $campos] = $this->preparar($modulo);
        $this->autorizar($config['roles']);
        $this->validarModuloOperador($modulo);

        $tabla = $config['tabla'];
        $consulta = DB::table($tabla);
        if ($modulo === 'compras' && auth()->user()->rol !== 'ADMINISTRADOR') {
            $columnasVisibles = array_keys(Esquema::columnas($tabla));
            $consulta->select(array_values(array_diff($columnasVisibles, ['costo_total'])));
        }
        if ($modulo === 'gastos') {
            AccesoGastosSupervisor::filtrar($consulta, $tabla);
        }
        $establecimiento = Esquema::resolver($tabla, ['establecimiento_id']);
        if ($establecimiento) {
            $consulta->where($establecimiento, $this->establecimientoId());
        }

        if ($this->esOperador() && $modulo === 'gastos') {
            $turnoCol = Esquema::resolver($tabla, ['turno_operativo_id']);
            abort_unless($turnoCol, 403, 'Los gastos deben estar vinculados a un turno operativo.');
            $trabajadorId = $this->exigirTrabajadorVinculado();
            $consulta->whereIn($turnoCol, function ($sub) use ($trabajadorId) {
                $sub->select('id')->from('turnos_operativos')
                    ->where('establecimiento_id', $this->establecimientoId())
                    ->where('trabajador_id', $trabajadorId);
            });
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $buscables = collect($campos)
                ->filter(fn ($c) => in_array($c['tipo'], ['text','textarea'], true))
                ->pluck('columna')->values()->all();
            if ($buscables) {
                $consulta->where(function ($sub) use ($buscables, $q) {
                    foreach ($buscables as $i => $columna) {
                        $i === 0
                            ? $sub->where($columna, 'like', "%{$q}%")
                            : $sub->orWhere($columna, 'like', "%{$q}%");
                    }
                });
            }
        }

        $fechaDesde = $request->query('desde');
        $fechaHasta = $request->query('hasta');
        $campoFecha = collect($campos)->first(fn ($c) => $c['tipo'] === 'date')['columna'] ?? null;
        if ($campoFecha && $fechaDesde) $consulta->whereDate($campoFecha, '>=', $fechaDesde);
        if ($campoFecha && $fechaHasta) $consulta->whereDate($campoFecha, '<=', $fechaHasta);

        $ordenFecha = $campoFecha ?: (Esquema::resolver($tabla, ['creado_en','id']) ?? 'id');
        $registros = $consulta->orderByDesc($ordenFecha)->orderByDesc('id')->paginate(20)->withQueryString();

        $opciones = $this->opcionesParaCampos($campos, $modulo);
        $total = $registros->total();
        $puedeEditar = ! ($this->esOperador() && $modulo === 'gastos');

        return view('crud.indice', compact(
            'modulo','config','campos','registros','opciones','q','fechaDesde','fechaHasta','campoFecha','total','puedeEditar'
        ));
    }

    public function crear(Request $request, string $modulo)
    {
        if ($modulo === 'descuentos') {
            return redirect()->route('descuentos.crear', $request->only('turno_operativo_id'));
        }

        [$config, $campos] = $this->preparar($modulo);
        $this->autorizar($config['roles']);
        $this->validarModuloOperador($modulo);

        $opciones = $this->opcionesParaCampos($campos, $modulo, true);
        $valoresPredeterminados = [];

        if (in_array($modulo, ['gastos', 'compras'], true)) {
            $esCompra = $modulo === 'compras';
            $fechaCampo = $esCompra ? 'fecha_compra' : 'fecha_gasto';
            $valoresPredeterminados[$fechaCampo] = now('America/Lima')->toDateString();

            $medio = $esCompra ? 'BBVA' : 'EFECTIVO';
            $medios = DB::table('medios_pago')
                ->where(function ($consulta) use ($medio) {
                    $consulta->where('codigo', $medio)->orWhere('nombre', $medio);
                    if ($medio === 'BBVA') {
                        $consulta->orWhere('nombre', 'like', '%BBVA%');
                    }
                });
            if (Esquema::tiene('medios_pago', 'establecimiento_id')) {
                $medios->where('establecimiento_id', $this->establecimientoId());
            }
            $valoresPredeterminados['medio_pago_id'] = $medios
                ->orderByRaw('CASE WHEN codigo = ? THEN 0 WHEN nombre = ? THEN 1 ELSE 2 END', [$medio, $medio])
                ->orderBy('id')->value('id');
        }
        if ($modulo === 'compras') {
            $valoresPredeterminados['galones'] = 1000;
            $valoresPredeterminados['nombre_proveedor'] = 'Petroperu - Planta Yurimaguas';
        }

        if ($modulo === 'gastos') {
            $turnoCol = Esquema::resolver($config['tabla'], ['turno_operativo_id']);
            $turnoId = $request->integer('turno_operativo_id') ?: null;

            if ($turnoId && $turnoCol) {
                PermisosSupervisor::exigirTurnoEditable($turnoId);
                $turno = DB::table('turnos_operativos')
                    ->where('id',$turnoId)
                    ->where('establecimiento_id',$this->establecimientoId())
                    ->first();

                if ($turno && (! $this->esOperador() || (int)$turno->trabajador_id === $this->exigirTrabajadorVinculado())) {
                    $valoresPredeterminados[$turnoCol] = $turnoId;
                }
            }

            if ($this->esOperador() && ($trabajadorCol = Esquema::resolver($config['tabla'], ['trabajador_id']))) {
                $valoresPredeterminados[$trabajadorCol] = $this->exigirTrabajadorVinculado();
            }
        }

        return view('crud.formulario', compact('modulo','config','campos','opciones','valoresPredeterminados') + [
            'registro'=>null,'esEdicion'=>false
        ]);
    }

    public function guardar(Request $request, string $modulo)
    {
        abort_if($modulo === 'descuentos', 403,
            'Los descuentos deben registrarse desde el formulario especializado.');

        [$config, $campos] = $this->preparar($modulo);
        $this->autorizar($config['roles']);
        $this->validarModuloOperador($modulo);

        $tabla = $config['tabla'];
        $this->autorizarCostoCompra($request, $modulo);
        $datos = $this->validarYMapear($request, $campos, $tabla);

        if ($this->esOperador() && $modulo === 'gastos') {
            $datos = $this->aplicarReglasGastoOperador($datos, $tabla);
        }

        if ($col = Esquema::resolver($tabla, ['establecimiento_id'])) {
            $datos[$col] = $this->establecimientoId();
        }

        $datos = Esquema::agregarCreadoEn($tabla, Esquema::filtrar($tabla, $datos));
        $id = DB::transaction(function () use ($request, $modulo, $tabla, $datos) {
            $turnoId = (int) ($datos['turno_operativo_id'] ?? 0);
            if ($modulo === 'gastos' && $turnoId > 0) {
                PermisosSupervisor::exigirTurnoEditable($turnoId, true);
            }

            $id = DB::table($tabla)->insertGetId($datos);
            ServicioAuditoria::registrar($tabla, $id, 'CREAR', null, $datos, $request);

            if ($modulo === 'gastos' && $turnoId > 0 && ! $this->esOperador()
                && (PermisosSupervisor::esSupervisor() || $request->filled('volver_turno_id'))) {
                app(\App\Services\ServicioFinancieroTurno::class)->sincronizar($turnoId);
            }

            return $id;
        }, 3);

        if ($this->esOperador() && $modulo === 'gastos') {
            return redirect()->route('crud.indice', 'gastos')->with('exito', 'Gasto del turno registrado correctamente.');
        }

        if ($modulo === 'gastos' && $request->filled('volver_turno_id')) {
            $turnoRetorno = (int) $request->input('volver_turno_id');

            if ($turnoRetorno > 0) {
                return redirect()->route('turnos.mostrar', $turnoRetorno)
                    ->with('exito', 'Gasto registrado. El resumen financiero del turno fue recalculado.');
            }
        }

        return redirect()->route('crud.editar', [$modulo, $id])->with('exito', ucfirst($config['singular']).' registrada correctamente.');
    }

    public function editar(string $modulo, int $id)
    {
        if ($modulo === 'descuentos') {
            return redirect()->route('descuentos.editar', $id);
        }
        [$config, $campos] = $this->preparar($modulo);
        $this->autorizar($config['roles']);
        $this->validarModuloOperador($modulo);
        abort_if($this->esOperador() && $modulo === 'gastos', 403, 'El operador puede registrar y consultar gastos de sus turnos, pero no editarlos después de guardarlos.');

        $registro = $this->registro($config['tabla'], $id, $modulo);
        if ($modulo === 'gastos' && $registro->turno_operativo_id !== null) {
            PermisosSupervisor::exigirTurnoEditable((int) $registro->turno_operativo_id);
        }
        $opciones = $this->opcionesParaCampos($campos, $modulo, true);
        $valoresPredeterminados = [];
        return view('crud.formulario', compact('modulo','config','campos','opciones','registro','valoresPredeterminados') + ['esEdicion'=>true]);
    }

    public function actualizar(Request $request, string $modulo, int $id)
    {
        abort_if($modulo === 'descuentos', 403,
            'Los descuentos deben modificarse desde el formulario especializado.');
        [$config, $campos] = $this->preparar($modulo);
        $this->autorizar($config['roles']);
        $this->validarModuloOperador($modulo);
        abort_if($this->esOperador() && $modulo === 'gastos', 403, 'El operador no puede modificar gastos ya registrados.');

        $tabla = $config['tabla'];
        $this->autorizarCostoCompra($request, $modulo);
        $datos = Esquema::agregarActualizadoEn($tabla, Esquema::filtrar($tabla, $this->validarYMapear($request, $campos, $tabla)));
        DB::transaction(function () use ($request, $modulo, $id, $tabla, $datos) {
            $antes = (array) $this->registro($tabla, $id, $modulo, true);
            $turnos = [];
            if ($modulo === 'gastos' && PermisosSupervisor::esSupervisor()) {
                $turnos = collect([(int) ($antes['turno_operativo_id'] ?? 0), (int) ($datos['turno_operativo_id'] ?? 0)])
                    ->filter()->unique()->sort()->all();
                foreach ($turnos as $turnoId) {
                    PermisosSupervisor::exigirTurnoEditable($turnoId, true);
                }
            }

            DB::table($tabla)->where('id', $id)->update($datos);
            ServicioAuditoria::registrar($tabla, $id, 'ACTUALIZAR', $antes, $datos, $request);
            foreach ($turnos as $turnoId) {
                app(\App\Services\ServicioFinancieroTurno::class)->sincronizar($turnoId);
            }
        }, 3);

        return back()->with('exito', 'Cambios guardados correctamente.');
    }

    private function autorizarCostoCompra(Request $request, string $modulo): void
    {
        abort_if($modulo === 'compras' && auth()->user()->rol !== 'ADMINISTRADOR'
            && $request->has('costo_total'), 403,
            'El costo total de la compra solo lo registra el Administrador.');
    }

    private function validarModuloOperador(string $modulo): void
    {
        if ($this->esOperador()) {
            abort_unless($modulo === 'gastos', 403);
            $this->exigirTrabajadorVinculado();
        }
    }

    private function aplicarReglasGastoOperador(array $datos, string $tabla): array
    {
        $turnoCol = Esquema::resolver($tabla, ['turno_operativo_id']);
        if (! $turnoCol || empty($datos[$turnoCol])) {
            throw ValidationException::withMessages([
                $turnoCol ?: 'turno_operativo_id' => 'El operador debe registrar el gasto dentro de uno de sus turnos.'
            ]);
        }

        $turno = DB::table('turnos_operativos')
            ->where('id',(int)$datos[$turnoCol])
            ->where('establecimiento_id',$this->establecimientoId())
            ->where('trabajador_id',$this->exigirTrabajadorVinculado())
            ->first();

        if (! $turno) {
            throw ValidationException::withMessages([
                $turnoCol => 'Solo puedes registrar gastos en tus propios turnos.'
            ]);
        }

        if ($fechaCol = Esquema::resolver($tabla, ['fecha_gasto','fecha'])) {
            $datos[$fechaCol] = $turno->fecha_operacion;
        }
        if ($trabajadorCol = Esquema::resolver($tabla, ['trabajador_id'])) {
            $datos[$trabajadorCol] = $this->exigirTrabajadorVinculado();
        }

        return $datos;
    }

    private function preparar(string $modulo): array
    {
        $config = config("grifo.crud.{$modulo}");
        abort_unless(is_array($config), 404);
        $config['roles'] ??= ['ADMINISTRADOR'];
        if ($modulo === 'compras' && ! in_array('SUPERVISOR', $config['roles'], true)) {
            $config['roles'][] = 'SUPERVISOR';
        }
        abort_unless(Esquema::existeTabla($config['tabla']), 404, 'La tabla del módulo no existe.');

        $campos = [];
        foreach ($config['campos'] as $campo) {
            $columna = Esquema::resolver($config['tabla'], $campo['columnas']);
            if (! $columna) continue;
            if ($modulo === 'compras' && auth()->user()->rol !== 'ADMINISTRADOR' && $columna === 'costo_total') {
                continue;
            }
            $campo['columna'] = $columna;
            $campo['nombre'] = $columna;
            if (($campo['tipo'] ?? '') === 'enum') {
                $campo['valores'] = Esquema::enum($config['tabla'], $columna);
            }
            $campos[] = $campo;
        }
        return [$config, $campos];
    }

    private function validarYMapear(Request $request, array $campos, string $tabla): array
    {
        $reglas = [];
        foreach ($campos as $campo) {
            $r = [];
            $r[] = ($campo['required'] ?? false) ? 'required' : 'nullable';
            if (in_array($campo['tipo'], ['number'], true)) $r[] = 'numeric';
            if (in_array($campo['tipo'], ['date'], true)) $r[] = 'date';
            if ($campo['tipo'] === 'bool') $r[] = 'boolean';
            if ($campo['tipo'] === 'enum' && ! empty($campo['valores'])) $r[] = Rule::in($campo['valores']);
            if ($campo['tipo'] === 'fk' && isset($campo['opciones']['tabla'])) $r[] = Rule::exists($campo['opciones']['tabla'], 'id');
            if ($campo['tipo'] === 'turno') $r[] = Rule::exists('turnos_operativos', 'id');
            $reglas[$campo['columna']] = $r;
        }

        $validados = $request->validate($reglas);
        foreach ($campos as $campo) {
            if ($campo['tipo'] === 'bool') {
                $validados[$campo['columna']] = $request->boolean($campo['columna']);
            }
            if (array_key_exists($campo['columna'], $validados) && $validados[$campo['columna']] === '') {
                $validados[$campo['columna']] = null;
            }
        }

        return $validados;
    }

    private function registro(string $tabla, int $id, ?string $modulo = null, bool $bloquear = false): object
    {
        $q = DB::table($tabla)->where('id', $id);
        if ($col = Esquema::resolver($tabla, ['establecimiento_id'])) {
            $q->where($col, $this->establecimientoId());
        }

        if ($modulo === 'gastos') {
            AccesoGastosSupervisor::filtrar($q, $tabla);
        }
        if ($modulo === 'compras' && auth()->user()->rol !== 'ADMINISTRADOR') {
            $q->select(array_values(array_diff(array_keys(Esquema::columnas($tabla)), ['costo_total'])));
        }

        if ($this->esOperador() && $modulo === 'gastos') {
            $turnoCol = Esquema::resolver($tabla, ['turno_operativo_id']);
            $trabajadorId = $this->exigirTrabajadorVinculado();
            $q->whereIn($turnoCol, function ($sub) use ($trabajadorId) {
                $sub->select('id')->from('turnos_operativos')
                    ->where('establecimiento_id',$this->establecimientoId())
                    ->where('trabajador_id',$trabajadorId);
            });
        }

        if ($bloquear) {
            $q->lockForUpdate();
        }

        return $q->firstOrFail();
    }

    private function opcionesParaCampos(array $campos, ?string $modulo = null, bool $formulario = false): array
    {
        $opciones = [];

        foreach ($campos as $campo) {
            if ($campo['tipo'] === 'fk') {
                $cfg = $campo['opciones'];
                $tabla = $cfg['tabla'];
                if (! Esquema::existeTabla($tabla)) continue;

                $q = DB::table($tabla);
                if (Esquema::tiene($tabla, 'establecimiento_id')) {
                    $q->where('establecimiento_id', $this->establecimientoId());
                }

                if ($this->esOperador() && $modulo === 'gastos' && $tabla === 'trabajadores') {
                    $q->where('id', $this->exigirTrabajadorVinculado());
                }

                $filas = $q->orderBy(Esquema::resolver($tabla, ['nombre','nombre_completo','codigo','id']) ?? 'id')->get();
                $opciones[$campo['columna']] = $filas->mapWithKeys(function ($fila) use ($cfg) {
                    $partes = [];
                    foreach ($cfg['texto'] as $c) {
                        if (isset($fila->{$c}) && $fila->{$c} !== '') $partes[] = $fila->{$c};
                    }
                    return [$fila->id => implode(' · ', $partes) ?: '#'.$fila->id];
                })->all();
            } elseif ($campo['tipo'] === 'turno' && Esquema::existeTabla('turnos_operativos')) {
                $q = DB::table('turnos_operativos as t')
                    ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
                    ->leftJoin('trabajadores as tr', 'tr.id', '=', 't.trabajador_id')
                    ->where('t.establecimiento_id', $this->establecimientoId());

                if ($this->esOperador() && $modulo === 'gastos') {
                    $q->where('t.trabajador_id', $this->exigirTrabajadorVinculado());
                }
                if ($formulario && $modulo === 'gastos') {
                    PermisosSupervisor::filtrarTurnosEditables($q);
                }

                $opciones[$campo['columna']] = $q
                    ->orderByDesc('t.fecha_operacion')->orderByDesc('t.id')->limit(250)
                    ->get(['t.id','t.fecha_operacion','tt.codigo','tr.nombre_completo'])
                    ->mapWithKeys(fn ($r) => [
                        $r->id => sprintf('%s · %s · %s', $r->fecha_operacion, $r->codigo ?? 'Turno', $r->nombre_completo ?? 'Sin trabajador')
                    ])->all();
            }
        }

        return $opciones;
    }
}
