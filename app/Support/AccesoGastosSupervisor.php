<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Restringe el detalle de gastos al autor Supervisor actual u operadores. */
final class AccesoGastosSupervisor
{
    public static function filtrar(Builder $consulta, string $alias = 'gastos'): Builder
    {
        if (! PermisosSupervisor::esSupervisor()) {
            return $consulta;
        }

        // Alias nunca puede proceder de la solicitud HTTP.
        abort_unless(in_array($alias, ['gastos', 'g'], true), 500);
        $usuarioActual = (int) auth()->id();
        $establecimiento = (int) auth()->user()->establecimiento_id;

        return $consulta->whereExists(function (Builder $sub) use ($alias, $usuarioActual, $establecimiento) {
            $sub->selectRaw('1')
                ->from('bitacora_auditoria as ga')
                ->join('usuarios as gu', 'gu.id', '=', 'ga.usuario_id')
                // Compara IDs como bytes ASCII (p. ej. '123' == '123'), evitando
                // el error SQL 1267 cuando la columna y CAST(id AS CHAR)
                // heredan collations utf8mb4 diferentes. No convierte a
                // numero: '123texto' nunca debe coincidir con el ID 123.
                ->whereRaw("CAST(ga.entidad_id AS BINARY) = CAST(CAST({$alias}.id AS CHAR) AS BINARY)")
                ->whereRaw('LOWER(ga.tipo_entidad) = ?', ['gastos'])
                ->where('ga.accion', 'CREAR')
                ->where('gu.establecimiento_id', $establecimiento)
                ->where(function (Builder $autores) use ($usuarioActual) {
                    $autores->where('ga.usuario_id', $usuarioActual)
                        ->orWhere('gu.rol', 'OPERADOR');
                });
        });
    }
}
