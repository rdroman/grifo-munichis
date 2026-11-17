<?php

namespace App\Support;

use Closure;
use Illuminate\Validation\Rules\Password;

final class ClaveMunichis
{
    public const AYUDA = 'Use al menos 12 caracteres, con mayúscula, minúscula, número y símbolo. Máximo 72 bytes.';

    public static function reglas(): array
    {
        return [
            'bail',
            'required',
            'string',
            'confirmed',
            Password::min(12)->mixedCase()->numbers(),
            'regex:/[\p{P}\p{S}]/u',
            static function (string $atributo, string $valor, Closure $error): void {
                if (strlen($valor) > 72 || str_contains($valor, "\0")) {
                    $error('La contraseña debe tener hasta 72 bytes y no puede contener caracteres nulos.');
                }
            },
        ];
    }

    public static function mensajes(): array
    {
        return [
            'password.required' => 'Ingrese la nueva contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => self::AYUDA,
            'password.mixed' => self::AYUDA,
            'password.numbers' => self::AYUDA,
            'password.regex' => self::AYUDA,
        ];
    }
}
