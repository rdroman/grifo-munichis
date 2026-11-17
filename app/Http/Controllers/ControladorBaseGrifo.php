<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

abstract class ControladorBaseGrifo extends Controller
{
    protected function establecimientoId(): int
    {
        return (int) auth()->user()->establecimiento_id;
    }

    protected function autorizar(array $roles): void
    {
        abort_unless(in_array(auth()->user()->rol, $roles, true), 403);
    }

    protected function puedeGestionar(): bool
    {
        return in_array(auth()->user()->rol, ['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR'], true);
    }

    protected function puedeFinanzas(): bool
    {
        return in_array(auth()->user()->rol, ['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD'], true);
    }

    protected function esOperador(): bool
    {
        return auth()->user()->rol === 'OPERADOR';
    }

    protected function trabajadorUsuarioId(): ?int
    {
        $id = auth()->user()->trabajador_id;

        return $id ? (int) $id : null;
    }

    protected function exigirTrabajadorVinculado(): int
    {
        $id = $this->trabajadorUsuarioId();

        abort_unless(
            $id,
            403,
            'La cuenta del operador no está vinculada a un trabajador. Solicite al administrador que vincule su usuario.'
        );

        $activo = DB::table('trabajadores')
            ->where('id', $id)
            ->where('establecimiento_id', $this->establecimientoId())
            ->where('activo', 1)
            ->exists();

        abort_unless(
            $activo,
            403,
            'El trabajador vinculado a esta cuenta no se encuentra activo.'
        );

        return $id;
    }

    protected function turnoPropio(int $turnoId): object
    {
        $q = DB::table('turnos_operativos')
            ->where('id', $turnoId)
            ->where('establecimiento_id', $this->establecimientoId());

        if ($this->esOperador()) {
            $q->where('trabajador_id', $this->exigirTrabajadorVinculado());
        }

        $turno = $q->first();
        abort_unless($turno, 404);

        return $turno;
    }

    protected function aplicarAlcanceTurnosOperador(Builder $consulta, string $alias = 't'): Builder
    {
        if ($this->esOperador()) {
            $consulta->where($alias . '.trabajador_id', $this->exigirTrabajadorVinculado());
        }

        return $consulta;
    }
}
