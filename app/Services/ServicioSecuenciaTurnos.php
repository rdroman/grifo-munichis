<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ServicioSecuenciaTurnos
{
    public function resolver(
        int $establecimientoId,
        string $modo = 'regular',
        bool $bloquear = false
    ): array {
        $modo = $modo === 'extraordinario' ? 'extraordinario' : 'regular';

        $tipos = DB::table('tipos_turno')
            ->whereIn('codigo', ['T1','T2','T3','T4','T5','T6'])
            ->get(['id','codigo','nombre'])
            ->keyBy('codigo');

        foreach (['T1','T2','T3'] as $codigo) {
            if (! $tipos->has($codigo)) {
                return $this->noDisponible(
                    "No existe el tipo de turno {$codigo} en el catálogo."
                );
            }
        }

        if ($modo === 'regular') {
            $consulta = DB::table('turnos_operativos as t')
                ->join('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
                ->where('t.establecimiento_id', $establecimientoId)
                ->whereIn('tt.codigo', ['T1','T2','T3'])
                ->orderByDesc('t.fecha_operacion')
                ->orderByDesc('t.id')
                ->select(
                    't.id',
                    't.fecha_operacion',
                    'tt.codigo',
                    'tt.nombre'
                );

            $this->bloquearSiCorresponde($consulta, $bloquear);

            $ultimoRegular = $consulta->first();

            if (! $ultimoRegular) {
                $tipo = $tipos->get('T1');

                return $this->respuesta(
                    Carbon::now('America/Lima')->toDateString(),
                    $tipo,
                    'regular'
                );
            }

            $fecha = Carbon::parse(
                (string) $ultimoRegular->fecha_operacion,
                'America/Lima'
            );

            $siguienteCodigo = match ((string) $ultimoRegular->codigo) {
                'T1' => 'T2',
                'T2' => 'T3',
                'T3' => 'T1',
                default => null,
            };

            if ($siguienteCodigo === null) {
                return $this->noDisponible(
                    'No se pudo determinar el siguiente turno regular.'
                );
            }

            if ((string) $ultimoRegular->codigo === 'T3') {
                $fecha->addDay();
            }

            $tipo = $tipos->get($siguienteCodigo);

            $duplicado = DB::table('turnos_operativos')
                ->where('establecimiento_id', $establecimientoId)
                ->whereDate('fecha_operacion', $fecha->toDateString())
                ->where('tipo_turno_id', $tipo->id)
                ->exists();

            if ($duplicado) {
                return $this->noDisponible(
                    'La secuencia regular contiene un registro duplicado o inconsistente. '
                    . 'Revise los turnos antes de continuar.'
                );
            }

            return $this->respuesta(
                $fecha->toDateString(),
                $tipo,
                'regular'
            );
        }

        foreach (['T4','T5','T6'] as $codigo) {
            if (! $tipos->has($codigo)) {
                return $this->noDisponible(
                    "No existe el tipo de turno extraordinario {$codigo} en el catálogo."
                );
            }
        }

        $consultaUltimo = DB::table('turnos_operativos as t')
            ->join('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->where('t.establecimiento_id', $establecimientoId)
            ->orderByDesc('t.fecha_operacion')
            ->orderByDesc('t.id')
            ->select(
                't.id',
                't.fecha_operacion',
                'tt.codigo',
                'tt.nombre'
            );

        $this->bloquearSiCorresponde($consultaUltimo, $bloquear);

        $ultimoFisico = $consultaUltimo->first();

        if (! $ultimoFisico) {
            return $this->noDisponible(
                'Primero debe existir un turno operativo antes de registrar un corte extraordinario.'
            );
        }

        $fecha = (string) $ultimoFisico->fecha_operacion;

        $consultaUsados = DB::table('turnos_operativos as t')
            ->join('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->where('t.establecimiento_id', $establecimientoId)
            ->whereDate('t.fecha_operacion', $fecha)
            ->whereIn('tt.codigo', ['T4','T5','T6'])
            ->orderBy('t.id')
            ->select('t.id', 'tt.codigo');

        $this->bloquearSiCorresponde($consultaUsados, $bloquear);

        $usados = $consultaUsados->get()
            ->pluck('codigo')
            ->all();

        $codigoDisponible = null;

        foreach (['T4','T5','T6'] as $codigo) {
            if (! in_array($codigo, $usados, true)) {
                $codigoDisponible = $codigo;
                break;
            }
        }

        if ($codigoDisponible === null) {
            return $this->noDisponible(
                "Ya se registraron T4, T5 y T6 para la fecha {$fecha}. "
                . 'No existe otro código extraordinario disponible.'
            );
        }

        return $this->respuesta(
            $fecha,
            $tipos->get($codigoDisponible),
            'extraordinario'
        );
    }

    private function bloquearSiCorresponde(
        Builder $consulta,
        bool $bloquear
    ): void {
        if ($bloquear) {
            $consulta->lockForUpdate();
        }
    }

    private function respuesta(
        string $fecha,
        object $tipo,
        string $modo
    ): array {
        return [
            'disponible' => true,
            'mensaje' => null,
            'modo' => $modo,
            'fecha' => $fecha,
            'tipo_turno_id' => (int) $tipo->id,
            'codigo' => (string) $tipo->codigo,
            'nombre' => (string) ($tipo->nombre ?? ''),
        ];
    }

    private function noDisponible(string $mensaje): array
    {
        return [
            'disponible' => false,
            'mensaje' => $mensaje,
            'modo' => null,
            'fecha' => null,
            'tipo_turno_id' => null,
            'codigo' => null,
            'nombre' => null,
        ];
    }
}
