<?php

namespace App\Services;

use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServicioAuditoria
{
    public static function registrar(
        string $tipoEntidad,
        string|int $entidadId,
        string $accion,
        ?array $antes,
        ?array $despues,
        ?Request $request = null
    ): void {
        if (! Esquema::existeTabla('bitacora_auditoria')) {
            return;
        }

        $permitidas = Esquema::enum('bitacora_auditoria', 'accion');
        if ($permitidas !== [] && ! in_array($accion, $permitidas, true)) {
            return;
        }

        DB::table('bitacora_auditoria')->insert(Esquema::filtrar('bitacora_auditoria', [
            'usuario_id' => auth()->id(),
            'tipo_entidad' => mb_strtoupper($tipoEntidad),
            'entidad_id' => (string) $entidadId,
            'accion' => $accion,
            'antes_json' => $antes === null ? null : json_encode($antes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'despues_json' => $despues === null ? null : json_encode($despues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'direccion_ip' => $request?->ip() ?? request()->ip(),
            'creado_en' => now(),
        ]));
    }
}
