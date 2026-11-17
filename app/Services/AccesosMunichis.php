<?php

namespace App\Services;

use App\Models\Usuario;
use App\Support\TokenMunichis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccesosMunichis
{
    public static function registrar(
        Request $request,
        Usuario $usuario,
        string $accion,
        ?string $metodo = null
    ): void {
        $datos = [
            'nombre' => $usuario->nombre,
            'correo_electronico' => $usuario->correo_electronico,
            'rol' => $usuario->rol,
            'establecimiento_id' => (string) $usuario->establecimiento_id,
        ];
        if ($metodo !== null) {
            $datos['metodo'] = $metodo;
        }

        DB::table('bitacora_auditoria')->insert([
            'usuario_id' => $usuario->id,
            'tipo_entidad' => 'USUARIO',
            'entidad_id' => (string) $usuario->id,
            'accion' => $accion,
            'antes_json' => null,
            'despues_json' => json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'direccion_ip' => $request->ip(),
            'creado_en' => now(),
        ]);
    }

    public static function confirmar(Request $request, Usuario $usuario, string $metodo): void
    {
        DB::table('usuarios')->where('id', $usuario->id)->update(['ultimo_acceso_en' => now()]);
        self::registrar($request, $usuario, 'INICIAR_SESION', $metodo);
    }

    public static function consumirToken(Usuario $usuario, string $entrada): ?string
    {
        $codigo = strtoupper(str_replace([' ', '-'], '', $entrada));
        if (preg_match('/\A[0-9]{6}\z/', $codigo)) {
            $paso = TokenMunichis::pasoValido($usuario->munichis_totp_secreto, $codigo, $usuario->munichis_totp_ultimo_paso);
            if ($paso === null) {
                return null;
            }
            $usuario->munichis_totp_ultimo_paso = $paso;

            return 'AUTENTICADOR';
        }

        $recuperacion = $usuario->munichis_totp_recuperacion ?? [];
        $indice = array_search(hash('sha256', $codigo), $recuperacion, true);
        if ($indice === false) {
            return null;
        }
        unset($recuperacion[$indice]);
        $usuario->munichis_totp_recuperacion = array_values($recuperacion);

        return 'RECUPERACION';
    }
}
