<?php

namespace App\Http\Controllers;

use App\Models\Trabajador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ControladorTrabajadores extends Controller
{
    public function indice(Request $request)
    {
        $this->autorizarGestion();

        $establecimientoId = auth()->user()->establecimiento_id;
        $texto = trim((string) $request->query('q', ''));
        $estado = (string) $request->query('estado', 'activos');

        $consulta = Trabajador::query()
            ->where('trabajadores.establecimiento_id', $establecimientoId)
            ->leftJoin('usuarios as u', 'u.trabajador_id', '=', 'trabajadores.id')
            ->select([
                'trabajadores.*',
                'u.id as usuario_id',
                'u.correo_electronico as usuario_correo',
                'u.activo as usuario_activo',
                'u.rol as usuario_rol',
            ]);

        if ($texto !== '') {
            $consulta->where(function ($q) use ($texto) {
                $q->where('trabajadores.nombre_completo', 'like', "%{$texto}%")
                    ->orWhere('trabajadores.numero_documento', 'like', "%{$texto}%");
            });
        }

        if ($estado === 'activos') {
            $consulta->where('trabajadores.activo', 1);
        } elseif ($estado === 'inactivos') {
            $consulta->where('trabajadores.activo', 0);
        }

        $trabajadores = $consulta
            ->orderByDesc('trabajadores.activo')
            ->orderBy('trabajadores.nombre_completo')
            ->paginate(12)
            ->withQueryString();

        $metricas = [
            'total' => Trabajador::where('establecimiento_id', $establecimientoId)->count(),
            'activos' => Trabajador::where('establecimiento_id', $establecimientoId)->where('activo', 1)->count(),
            'inactivos' => Trabajador::where('establecimiento_id', $establecimientoId)->where('activo', 0)->count(),
            'con_cuenta' => Trabajador::query()
                ->where('trabajadores.establecimiento_id', $establecimientoId)
                ->join('usuarios as u', 'u.trabajador_id', '=', 'trabajadores.id')
                ->count(),
        ];

        return view('trabajadores.indice', compact(
            'trabajadores',
            'metricas',
            'texto',
            'estado'
        ));
    }

    public function crear()
    {
        $this->autorizarGestion();

        return view('trabajadores.formulario', [
            'trabajador' => null,
            'esEdicion' => false,
        ]);
    }

    public function guardar(Request $request)
    {
        $this->autorizarGestion();

        $establecimientoId = auth()->user()->establecimiento_id;
        $datos = $this->validarDatos($request, $establecimientoId);

        $trabajador = DB::transaction(function () use ($datos, $establecimientoId, $request) {
            $trabajador = Trabajador::create([
                'establecimiento_id' => $establecimientoId,
                ...$datos,
            ]);

            $this->registrarAuditoria(
                'CREAR',
                $trabajador,
                null,
                $this->datosAuditables($trabajador),
                $request
            );

            return $trabajador;
        });

        return redirect()
            ->route('trabajadores.editar', $trabajador)
            ->with('exito', 'Trabajador registrado correctamente.');
    }

    public function editar(Trabajador $trabajador)
    {
        $this->autorizarTrabajador($trabajador);

        $trabajador->load('usuario');

        return view('trabajadores.formulario', [
            'trabajador' => $trabajador,
            'esEdicion' => true,
        ]);
    }

    public function actualizar(Request $request, Trabajador $trabajador)
    {
        $this->autorizarTrabajador($trabajador);

        $establecimientoId = auth()->user()->establecimiento_id;
        $datos = $this->validarDatos($request, $establecimientoId, $trabajador->id);
        $antes = $this->datosAuditables($trabajador);

        DB::transaction(function () use ($trabajador, $datos, $antes, $request) {
            $trabajador->fill($datos);
            $trabajador->save();

            $this->registrarAuditoria(
                'ACTUALIZAR',
                $trabajador,
                $antes,
                $this->datosAuditables($trabajador->fresh()),
                $request
            );
        });

        return redirect()
            ->route('trabajadores.editar', $trabajador)
            ->with('exito', 'Datos del trabajador actualizados correctamente.');
    }

    public function cambiarEstado(Request $request, Trabajador $trabajador)
    {
        $this->autorizarTrabajador($trabajador);

        $antes = $this->datosAuditables($trabajador);
        $nuevoEstado = ! (bool) $trabajador->activo;

        DB::transaction(function () use ($trabajador, $nuevoEstado, $antes, $request) {
            $trabajador->activo = $nuevoEstado;
            $trabajador->save();

            $this->registrarAuditoria(
                'ACTUALIZAR',
                $trabajador,
                $antes,
                $this->datosAuditables($trabajador->fresh()),
                $request
            );
        });

        return back()->with(
            'exito',
            $nuevoEstado
                ? 'Trabajador activado correctamente.'
                : 'Trabajador desactivado correctamente.'
        );
    }

    private function validarDatos(Request $request, int $establecimientoId, ?int $trabajadorId = null): array
    {
        $reglaNombreUnico = Rule::unique('trabajadores', 'nombre_completo')
            ->where(fn ($q) => $q->where('establecimiento_id', $establecimientoId));

        if ($trabajadorId !== null) {
            $reglaNombreUnico->ignore($trabajadorId);
        }

        $datos = $request->validate(
            [
                'nombre_completo' => ['required', 'string', 'max:150', $reglaNombreUnico],
                'numero_documento' => ['nullable', 'string', 'max:20'],
                'fecha_ingreso' => ['nullable', 'date'],
                'fecha_cese' => ['nullable', 'date'],
                'activo' => ['required', 'boolean'],
                'observaciones' => ['nullable', 'string', 'max:2000'],
            ],
            [
                'nombre_completo.required' => 'Ingrese el nombre del trabajador.',
                'nombre_completo.max' => 'El nombre no puede superar 150 caracteres.',
                'nombre_completo.unique' => 'Ya existe un trabajador con ese nombre en el establecimiento.',
                'numero_documento.max' => 'El número de documento no puede superar 20 caracteres.',
                'fecha_ingreso.date' => 'La fecha de ingreso no es válida.',
                'fecha_cese.date' => 'La fecha de cese no es válida.',
                'activo.required' => 'Indique el estado del trabajador.',
            ]
        );

        if (
            ! empty($datos['fecha_ingreso'])
            && ! empty($datos['fecha_cese'])
            && $datos['fecha_cese'] < $datos['fecha_ingreso']
        ) {
            throw ValidationException::withMessages([
                'fecha_cese' => 'La fecha de cese no puede ser anterior a la fecha de ingreso.',
            ]);
        }

        $datos['nombre_completo'] = trim($datos['nombre_completo']);
        $datos['numero_documento'] = isset($datos['numero_documento'])
            ? trim($datos['numero_documento']) ?: null
            : null;
        $datos['observaciones'] = isset($datos['observaciones'])
            ? trim($datos['observaciones']) ?: null
            : null;
        $datos['activo'] = (bool) $datos['activo'];

        return $datos;
    }

    private function autorizarGestion(): void
    {
        abort_unless(
            in_array(auth()->user()->rol, ['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR'], true),
            403
        );
    }

    private function autorizarTrabajador(Trabajador $trabajador): void
    {
        $this->autorizarGestion();

        abort_unless(
            (int) $trabajador->establecimiento_id === (int) auth()->user()->establecimiento_id,
            404
        );
    }

    private function datosAuditables(Trabajador $trabajador): array
    {
        return [
            'establecimiento_id' => (int) $trabajador->establecimiento_id,
            'nombre_completo' => $trabajador->nombre_completo,
            'numero_documento' => $trabajador->numero_documento,
            'activo' => (bool) $trabajador->activo,
            'fecha_ingreso' => optional($trabajador->fecha_ingreso)->format('Y-m-d'),
            'fecha_cese' => optional($trabajador->fecha_cese)->format('Y-m-d'),
            'observaciones' => $trabajador->observaciones,
        ];
    }

    private function registrarAuditoria(
        string $accion,
        Trabajador $trabajador,
        ?array $antes,
        ?array $despues,
        Request $request
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'usuario_id' => auth()->id(),
            'tipo_entidad' => 'TRABAJADOR',
            'entidad_id' => (string) $trabajador->id,
            'accion' => $accion,
            'antes_json' => $antes === null
                ? null
                : json_encode($antes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'despues_json' => $despues === null
                ? null
                : json_encode($despues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'direccion_ip' => $request->ip(),
            'creado_en' => now(),
        ]);
    }
}
