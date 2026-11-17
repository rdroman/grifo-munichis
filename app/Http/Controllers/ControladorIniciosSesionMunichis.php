<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Support\Esquema;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ControladorIniciosSesionMunichis extends ControladorBaseGrifo
{
    public function indice(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR']);
        $establecimiento = $this->establecimientoId();
        $roles = Esquema::enum('usuarios', 'rol');
        $metodos = [
            'CLAVE' => 'Contraseña',
            'AUTENTICADOR' => 'Aplicación autenticadora',
            'RECUPERACION' => 'Código de recuperación',
            'HISTORICO' => 'Histórico',
        ];
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('desde'), 'after_or_equal:desde')],
            'usuario' => ['nullable', 'integer', Rule::exists('usuarios', 'id')->where('establecimiento_id', $establecimiento)],
            'rol' => ['nullable', Rule::in($roles)],
            'ip' => ['nullable', 'ip'],
            'metodo' => ['nullable', Rule::in(array_keys($metodos))],
        ]);

        $filtros['desde'] = $request->has('desde') ? ($filtros['desde'] ?? null) : now()->subDays(29)->toDateString();
        $filtros['hasta'] = $request->has('hasta') ? ($filtros['hasta'] ?? null) : now()->toDateString();
        $usuarios = Usuario::query()->where('establecimiento_id', $establecimiento)->orderBy('nombre')->get(['id', 'nombre']);
        $consulta = DB::table('bitacora_auditoria as b')
            ->leftJoin('usuarios as u', 'u.id', '=', 'b.usuario_id')
            ->where('b.tipo_entidad', 'USUARIO')
            ->where('b.accion', 'INICIAR_SESION')
            ->where(function ($q) use ($establecimiento) {
                $q->where('b.despues_json->establecimiento_id', (string) $establecimiento)
                    ->orWhere(function ($historico) use ($establecimiento) {
                        $historico->whereNull('b.despues_json->establecimiento_id')
                            ->where('u.establecimiento_id', $establecimiento);
                    });
            })
            ->select('b.creado_en', 'b.direccion_ip', 'b.despues_json', 'u.nombre', 'u.correo_electronico', 'u.rol');

        if ($filtros['desde']) {
            $consulta->where('b.creado_en', '>=', $filtros['desde'] . ' 00:00:00');
        }
        if ($filtros['hasta']) {
            $hastaExclusivo = Carbon::parse($filtros['hasta'])->addDay()->toDateString();
            $consulta->where('b.creado_en', '<', $hastaExclusivo . ' 00:00:00');
        }
        if (! empty($filtros['usuario'])) {
            $consulta->where('b.usuario_id', $filtros['usuario']);
        }
        if (! empty($filtros['rol'])) {
            $consulta->where(function ($q) use ($filtros) {
                $q->where('b.despues_json->rol', $filtros['rol'])
                    ->orWhere(function ($historico) use ($filtros) {
                        $historico->whereNull('b.despues_json->rol')->where('u.rol', $filtros['rol']);
                    });
            });
        }
        if (! empty($filtros['ip'])) {
            $consulta->where('b.direccion_ip', $filtros['ip']);
        }
        if (($filtros['metodo'] ?? null) === 'HISTORICO') {
            $consulta->whereNull('b.despues_json->metodo');
        } elseif (! empty($filtros['metodo'])) {
            $consulta->where('b.despues_json->metodo', $filtros['metodo']);
        }

        $registros = $consulta->orderByDesc('b.creado_en')->orderByDesc('b.id')->paginate(30)->withQueryString();

        return response()->view('seguridad-munichis.inicios', compact('registros', 'usuarios', 'roles', 'metodos', 'filtros'))
            ->header('Cache-Control', 'no-store');
    }
}
