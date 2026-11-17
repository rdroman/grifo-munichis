<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\AccesosMunichis;
use App\Support\SesionMunichis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

class ControladorAutenticacion extends Controller
{
    public function mostrarLogin()
    {
        return view('autenticacion.login');
    }

    public function iniciarSesion(Request $request)
    {
        $datos = $request->validate([
            'correo_electronico' => ['required', 'email', 'max:190'],
            'contrasena' => ['required', 'string'],
        ], [
            'correo_electronico.required' => 'Ingrese su correo electrónico.',
            'correo_electronico.email' => 'Ingrese un correo electrónico válido.',
            'contrasena.required' => 'Ingrese su contraseña.',
        ]);

        $request->session()->forget(SesionMunichis::PENDIENTE);
        $usuario = (new Timebox)->call(
            fn () => DB::transaction(function () use ($datos, $request) {
                $usuario = Usuario::query()
                    ->where('correo_electronico', $datos['correo_electronico'])
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->first();

                $proveedor = Auth::getProvider();
                $credenciales = ['password' => $datos['contrasena']];
                if (! $usuario || ! $proveedor->validateCredentials($usuario, $credenciales)) {
                    return null;
                }

                if (config('hashing.rehash_on_login', true) &&
                    strlen($datos['contrasena']) <= 72 && ! str_contains($datos['contrasena'], "\0")) {
                    $proveedor->rehashPasswordIfRequired($usuario, $credenciales);
                }

                if ($usuario->getRawOriginal('munichis_totp_secreto') === null) {
                    AccesosMunichis::confirmar($request, $usuario, 'CLAVE');
                }

                return $usuario;
            }),
            300000
        );

        if (! $usuario) {
            return back()->withErrors([
                'correo_electronico' => 'El correo electrónico o la contraseña son incorrectos.',
            ])->onlyInput('correo_electronico');
        }

        if ($usuario->getRawOriginal('munichis_totp_secreto') !== null) {
            $request->session()->regenerate(true);
            $request->session()->put(SesionMunichis::PENDIENTE, [
                'id' => $usuario->id,
                'huella' => SesionMunichis::huella($usuario),
                'vence' => time() + 300,
            ]);

            return redirect()->route('seguridad-munichis.token');
        }

        return $this->completar($request, $usuario);
    }

    public function mostrarToken(Request $request)
    {
        if (! $this->pendiente($request)) {
            return redirect()->route('login');
        }

        return response()->view('seguridad-munichis.token')->header('Cache-Control', 'no-store');
    }

    public function validarToken(Request $request)
    {
        $pendiente = $this->pendiente($request);
        if (! $pendiente) {
            return redirect()->route('login');
        }

        $datos = $request->validate(
            ['codigo' => ['required', 'string', 'max:32']],
            ['codigo.required' => 'Ingrese el código de verificación.']
        );
        $limite = 'munichis-token:' . $pendiente['id'];
        $usuario = DB::transaction(function () use ($pendiente, $datos, $request, $limite) {
            $usuario = Usuario::query()->lockForUpdate()->find($pendiente['id']);
            if (! $usuario || ! $usuario->activo || ! hash_equals($pendiente['huella'], SesionMunichis::huella($usuario))) {
                return null;
            }

            if (RateLimiter::tooManyAttempts($limite, 5)) {
                throw ValidationException::withMessages([
                    'codigo' => 'Demasiados intentos. Vuelva a intentarlo en unos minutos.',
                ]);
            }
            RateLimiter::hit($limite, 300);

            $metodo = AccesosMunichis::consumirToken($usuario, $datos['codigo']);
            if ($metodo === null) {
                return null;
            }

            $usuario->save();
            AccesosMunichis::confirmar($request, $usuario, $metodo);

            return $usuario;
        });

        if (! $usuario) {
            return back()->withErrors(['codigo' => 'Código inválido, vencido o ya utilizado.']);
        }

        RateLimiter::clear($limite);

        return $this->completar($request, $usuario);
    }

    public function cancelarToken(Request $request)
    {
        $request->session()->forget(SesionMunichis::PENDIENTE);
        $request->session()->regenerate(true);

        return redirect()->route('login');
    }

    public function cerrarSesion(Request $request)
    {
        if ($usuario = $request->user()) {
            AccesosMunichis::registrar($request, $usuario, 'CERRAR_SESION');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function completar(Request $request, Usuario $usuario)
    {
        $request->session()->forget(SesionMunichis::PENDIENTE);
        Auth::login($usuario);
        SesionMunichis::renovar($request, $usuario);

        return redirect()->intended(route('panel'));
    }

    private function pendiente(Request $request): ?array
    {
        $pendiente = $request->session()->get(SesionMunichis::PENDIENTE);
        if ($pendiente && $pendiente['vence'] > time()) {
            return $pendiente;
        }

        $request->session()->forget(SesionMunichis::PENDIENTE);

        return null;
    }
}
