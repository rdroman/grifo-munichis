<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PermisosSupervisor
{
    private const CAMPOS_VALIDACION = [
        'validado_efectivo_en',
        'usuario_validador_efectivo_id',
        'efectivo_administrador',
        'validado_digital_en',
        'usuario_validador_digital_id',
    ];

    private const ESTADOS_REVISADOS = ['VALIDADO', 'OBSERVADO'];

    public static function esSupervisor(): bool
    {
        return auth()->check() && auth()->user()->rol === 'SUPERVISOR';
    }

    public static function puedeVerEnMenu(string $clave): bool
    {
        if (! self::esSupervisor()) {
            return true;
        }

        return true;
    }

    public static function idDeParametro(mixed $valor): int
    {
        if (is_object($valor) && isset($valor->id)) {
            return (int) $valor->id;
        }

        return (int) $valor;
    }

    public static function turnoCerradoParaSupervisor(int $turnoId, bool $bloquear = false): bool
    {
        $consulta = DB::table('turnos_operativos as t')
            ->where('t.id', $turnoId)
            ->where('t.establecimiento_id', (int) auth()->user()->establecimiento_id);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        abort_unless($consulta->first(['t.id']), 404, 'Turno no encontrado.');

        $resumen = DB::table('resumen_financiero_turno as rf')
            ->where('rf.turno_operativo_id', $turnoId);

        if ($bloquear) {
            $resumen->lockForUpdate();
        }

        $registro = $resumen->first();
        if (! $registro) {
            return false;
        }

        foreach (self::CAMPOS_VALIDACION as $campo) {
            if ($registro->{$campo} !== null) {
                return true;
            }
        }

        return in_array($registro->estado_validacion_digital, self::ESTADOS_REVISADOS, true)
            || (float) $registro->digital_administrador > 0;
    }

    public static function filtrarTurnosEditables(Builder $consulta): void
    {
        if (self::esSupervisor()) {
            $consulta->whereNotExists(function (Builder $resumen) {
                $resumen->selectRaw('1')->from('resumen_financiero_turno as rf')
                    ->whereColumn('rf.turno_operativo_id', 't.id')
                    ->where(function (Builder $validado) {
                        $validado->whereIn('rf.estado_validacion_digital', self::ESTADOS_REVISADOS)
                            ->orWhere('rf.digital_administrador', '>', 0);
                        foreach (self::CAMPOS_VALIDACION as $campo) {
                            $validado->orWhereNotNull('rf.'.$campo);
                        }
                    });
            });
        }
    }

    public static function exigirTurnoEditable(int $turnoId, bool $bloquear = false): void
    {
        if (self::esSupervisor() && self::turnoCerradoParaSupervisor($turnoId, $bloquear)) {
            abort(403, 'El turno tiene una validación administrativa y el Supervisor solo puede consultarlo.');
        }
    }

    public static function exigirCreditoEditable(int $creditoId): void
    {
        if (! self::esSupervisor()) {
            return;
        }

        $turnoId = DB::table('ventas_credito')
            ->where('id', $creditoId)
            ->value('turno_operativo_id');

        if ($turnoId !== null) {
            self::exigirTurnoEditable((int) $turnoId);
        }
    }

    public static function exigirGastoEditable(int $gastoId): void
    {
        if (! self::esSupervisor()) {
            return;
        }

        $turnoId = DB::table('gastos')
            ->where('id', $gastoId)
            ->value('turno_operativo_id');

        if ($turnoId !== null) {
            self::exigirTurnoEditable((int) $turnoId);
        }
    }

    public static function prohibirEscrituraDescuentos(): void
    {
        if (self::esSupervisor()) {
            abort(
                403,
                'El rol Supervisor no tiene permiso para registrar ni modificar descuentos.'
            );
        }
    }
}
