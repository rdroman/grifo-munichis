<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\AccesosMunichis;
use App\Services\ServicioAuditoria;
use App\Support\ClaveMunichis;
use App\Support\SesionMunichis;
use App\Support\TokenMunichis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ControladorSeguridadMunichis extends Controller
{
    private const ALTA = 'munichis_alta_totp';

    public function indice(Request $request)
    {
        $usuario = $request->user();
        $activado = $usuario->getRawOriginal('munichis_totp_secreto') !== null;
        $alta = $this->alta($request);
        $secreto = ! $activado && $alta ? Crypt::decryptString($alta['secreto']) : null;
        $qr = $secreto === null ? null : $this->uriAutenticador($usuario, $secreto);

        return response()->view('seguridad-munichis.cuenta', compact('activado', 'secreto', 'qr'))
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'same-origin');
    }

    public function cambiarClave(Request $request)
    {
        $datos = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ClaveMunichis::reglas(),
        ], [
            ...ClaveMunichis::mensajes(),
            'current_password.required' => 'Ingrese su contraseña actual.',
        ]);

        $usuario = DB::transaction(function () use ($request, $datos) {
            $usuario = $this->usuarioConClave($request, $datos['current_password']);
            if (Hash::check($datos['password'], $usuario->getAuthPassword())) {
                throw ValidationException::withMessages(['password' => 'Elija una contraseña distinta de la actual.']);
            }
            $usuario->contrasena = Hash::make($datos['password']);
            $usuario->save();
            $this->auditar($request, $usuario, 'CAMBIAR_CONTRASENA');

            return $usuario;
        });

        $request->session()->forget(self::ALTA);
        SesionMunichis::renovar($request, $usuario);

        return back()->with('exito', 'Contraseña actualizada. Las otras sesiones se cerrarán al volver a usarse.');
    }

    public function prepararToken(Request $request)
    {
        $datos = $request->validate(['current_password' => ['required', 'string']], [
            'current_password.required' => 'Ingrese su contraseña actual.',
        ]);
        $usuario = DB::transaction(fn () => $this->usuarioConClave($request, $datos['current_password']));
        abort_if($usuario->getRawOriginal('munichis_totp_secreto') !== null, 409);

        SesionMunichis::renovar($request, $usuario);
        $request->session()->put(self::ALTA, [
            'secreto' => Crypt::encryptString(TokenMunichis::generarSecreto()),
            'huella' => SesionMunichis::huella($usuario),
            'vence' => time() + 600,
        ]);

        return redirect()->route('seguridad-munichis.cuenta');
    }

    public function activarToken(Request $request)
    {
        $alta = $this->alta($request);
        if (! $alta) {
            return redirect()->route('seguridad-munichis.cuenta')->with('error', 'La configuración venció. Iníciela de nuevo.');
        }

        $datos = $request->validate([
            'codigo' => ['required', 'digits:6'],
        ], [
            'codigo.required' => 'Ingrese el código de la aplicación.',
            'codigo.digits' => 'Ingrese un código de seis dígitos.',
        ]);

        [$usuario, $codigos] = DB::transaction(function () use ($request, $alta, $datos) {
            $usuario = $this->usuarioVigente($request);
            abort_unless(hash_equals($alta['huella'], SesionMunichis::huella($usuario)), 403);
            abort_if($usuario->getRawOriginal('munichis_totp_secreto') !== null, 409);

            $secreto = Crypt::decryptString($alta['secreto']);
            $paso = TokenMunichis::pasoValido($secreto, $datos['codigo'], null);
            if ($paso === null) {
                throw ValidationException::withMessages(['codigo' => 'Código inválido. Revise la hora automática del celular.']);
            }

            $codigos = [];
            for ($i = 0; $i < 8; $i++) {
                $codigos[] = strtoupper(bin2hex(random_bytes(10)));
            }

            $usuario->munichis_totp_secreto = $secreto;
            $usuario->munichis_totp_ultimo_paso = $paso;
            $usuario->munichis_totp_recuperacion = array_map(fn ($codigo) => hash('sha256', $codigo), $codigos);
            $usuario->save();
            $this->auditar($request, $usuario, 'ACTIVAR_SEGUNDO_FACTOR');

            return [$usuario, $codigos];
        });

        $request->session()->forget(self::ALTA);
        SesionMunichis::renovar($request, $usuario);

        return response()->view('seguridad-munichis.recuperacion', compact('codigos'))->header('Cache-Control', 'no-store');
    }

    public function desactivarToken(Request $request)
    {
        $datos = $request->validate([
            'current_password' => ['required', 'string'],
            'codigo' => ['required', 'string', 'max:32'],
        ], [
            'current_password.required' => 'Ingrese su contraseña actual.',
            'codigo.required' => 'Ingrese un código de verificación o de recuperación.',
        ]);

        $usuario = DB::transaction(function () use ($request, $datos) {
            $usuario = $this->usuarioConClave($request, $datos['current_password']);
            if ($usuario->getRawOriginal('munichis_totp_secreto') === null ||
                AccesosMunichis::consumirToken($usuario, $datos['codigo']) === null) {
                throw ValidationException::withMessages(['codigo' => 'Código inválido, vencido o ya utilizado.']);
            }

            $usuario->munichis_totp_secreto = null;
            $usuario->munichis_totp_ultimo_paso = null;
            $usuario->munichis_totp_recuperacion = null;
            $usuario->save();
            $this->auditar($request, $usuario, 'DESACTIVAR_SEGUNDO_FACTOR');

            return $usuario;
        });

        SesionMunichis::renovar($request, $usuario);

        return back()->with('exito', 'Verificación en dos pasos desactivada.');
    }

    private function alta(Request $request): ?array
    {
        $alta = $request->session()->get(self::ALTA);
        if ($alta && isset($alta['huella']) && $alta['vence'] > time() &&
            hash_equals($alta['huella'], SesionMunichis::huella($request->user()))) {
            return $alta;
        }

        $request->session()->forget(self::ALTA);

        return null;
    }

    private function uriAutenticador(Usuario $usuario, string $secreto): string
    {
        $emisor = 'Grifo Munichis';
        $cuenta = $usuario->correo_electronico ?: 'Usuario ' . $usuario->id;
        $parametros = http_build_query([
            'secret' => $secreto,
            'issuer' => $emisor,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . rawurlencode($emisor) . ':' . rawurlencode($cuenta) . '?' . $parametros;
    }

    private function usuarioVigente(Request $request): Usuario
    {
        $usuario = Usuario::query()->lockForUpdate()->findOrFail($request->user()->id);
        abort_unless(
            $usuario->activo && hash_equals(
                SesionMunichis::huella($usuario),
                (string) $request->session()->get(SesionMunichis::HUELLA, '')
            ),
            403
        );

        return $usuario;
    }

    private function usuarioConClave(Request $request, string $clave): Usuario
    {
        $usuario = $this->usuarioVigente($request);
        if (! Hash::check($clave, $usuario->getAuthPassword())) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual es incorrecta.']);
        }

        return $usuario;
    }

    private function auditar(Request $request, Usuario $usuario, string $evento): void
    {
        ServicioAuditoria::registrar('USUARIO', $usuario->id, 'ACTUALIZAR', null, ['evento' => $evento], $request);
    }
}
