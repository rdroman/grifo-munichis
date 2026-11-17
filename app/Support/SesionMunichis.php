<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Http\Request;

final class SesionMunichis
{
    public const HUELLA = 'munichis_huella_sesion';
    public const PENDIENTE = 'munichis_acceso_pendiente';

    public static function huella(Usuario $usuario): string
    {
        return hash_hmac('sha256', json_encode([
            $usuario->getKey(),
            $usuario->getAuthPassword(),
            $usuario->getRawOriginal('munichis_totp_secreto'),
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public static function renovar(Request $request, Usuario $usuario): void
    {
        $request->session()->regenerate(true);
        $request->session()->put(self::HUELLA, self::huella($usuario));
    }
}
