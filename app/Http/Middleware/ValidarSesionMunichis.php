<?php

namespace App\Http\Middleware;

use App\Support\SesionMunichis;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ValidarSesionMunichis
{
    public function handle(Request $request, Closure $siguiente): Response
    {
        $usuario = $request->user();
        if ($usuario && (! $usuario->activo || ! hash_equals(
            SesionMunichis::huella($usuario),
            (string) $request->session()->get(SesionMunichis::HUELLA, '')
        ))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Inicie sesión nuevamente.'], 401)
                : redirect()->route('login');
        }

        return $siguiente($request);
    }
}
