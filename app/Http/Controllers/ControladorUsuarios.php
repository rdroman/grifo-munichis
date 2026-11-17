<?php

namespace App\Http\Controllers;

use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\ServicioAuditoria;
use App\Support\ClaveMunichis;
use App\Support\Esquema;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ControladorUsuarios extends ControladorBaseGrifo
{
    public function indice()
    {
        $this->autorizar(['ADMINISTRADOR']);
        $usuarios = Usuario::query()->where('establecimiento_id', $this->establecimientoId())
            ->with('trabajador')->orderBy('nombre')->paginate(25);

        return view('configuracion.usuarios', compact('usuarios'));
    }

    public function crear()
    {
        $this->autorizar(['ADMINISTRADOR']);

        return $this->formulario(new Usuario(), false);
    }

    public function editar(Usuario $usuario)
    {
        $this->autorizarUsuario($usuario);

        return $this->formulario($usuario, true);
    }

    public function guardar(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR']);
        $datos = $this->validar($request);
        $datos['establecimiento_id'] = $this->establecimientoId();
        $datos['contrasena'] = Hash::make($datos['password']);
        unset($datos['password']);

        $usuario = DB::transaction(function () use ($datos, $request) {
            $usuario = Usuario::create($datos);
            ServicioAuditoria::registrar('USUARIO', $usuario->id, 'CREAR', null, $this->audit($usuario), $request);

            return $usuario;
        });

        return redirect()->route('usuarios.editar', $usuario)->with('exito', 'Usuario creado correctamente.');
    }

    public function actualizar(Request $request, Usuario $usuario)
    {
        $this->autorizarUsuario($usuario);
        $datos = $this->validar($request, $usuario->id);
        if ($usuario->id === $request->user()->id) {
            $datos['activo'] = true;
        }

        DB::transaction(function () use ($request, $usuario, $datos) {
            $usuario = Usuario::query()->lockForUpdate()->findOrFail($usuario->id);
            $this->autorizarUsuario($usuario);
            $antes = $this->audit($usuario);
            $usuario->fill($datos)->save();
            ServicioAuditoria::registrar('USUARIO', $usuario->id, 'ACTUALIZAR', $antes, $this->audit($usuario), $request);
        });

        return back()->with('exito', 'Usuario actualizado.');
    }

    public function restablecerClave(Request $request, Usuario $usuario)
    {
        $this->autorizarUsuario($usuario);
        abort_if($usuario->id === $request->user()->id, 403);

        $datos = $request->validateWithBag('restablecerClave', [
            'current_password' => ['bail', 'required', 'string', 'current_password'],
            'password' => ClaveMunichis::reglas(),
        ], [
            ...ClaveMunichis::mensajes(),
            'current_password.required' => 'Ingrese su propia contraseña de administrador.',
            'current_password.current_password' => 'Su contraseña de administrador es incorrecta.',
        ]);

        DB::transaction(function () use ($usuario, $datos, $request) {
            $usuario = Usuario::query()->lockForUpdate()->findOrFail($usuario->id);
            $this->autorizarUsuario($usuario);
            if ($usuario->getAuthPassword() && Hash::check($datos['password'], $usuario->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'password' => 'La contraseña nueva debe ser distinta de la anterior del usuario.',
                ])->errorBag('restablecerClave');
            }

            $usuario->contrasena = Hash::make($datos['password']);
            $usuario->save();
            ServicioAuditoria::registrar(
                'USUARIO', $usuario->id, 'ACTUALIZAR', null, ['evento' => 'RESTABLECER_CONTRASENA'], $request
            );
        });

        return back()->with('exito', 'Contraseña restablecida. El usuario deberá volver a iniciar sesión.');
    }

    private function formulario(Usuario $usuario, bool $esEdicion)
    {
        $trabajadores = Trabajador::where('establecimiento_id', $this->establecimientoId())
            ->orderBy('nombre_completo')->get();
        $roles = Esquema::enum('usuarios', 'rol');

        return view('configuracion.usuario-formulario', compact('usuario', 'esEdicion', 'trabajadores', 'roles'));
    }

    private function validar(Request $request, ?int $id = null): array
    {
        $telefono = $request->input('telefono');
        if (is_string($telefono)) {
            $telefono = preg_replace('/[\s().-]+/', '', $telefono);
            if (preg_match('/\A9[0-9]{8}\z/', $telefono)) {
                $telefono = '+51' . $telefono;
            }
            $request->merge(['telefono' => $telefono === '' ? null : $telefono]);
        }

        $reglas = [
            'nombre' => ['required', 'string', 'max:150'],
            'correo_electronico' => ['required', 'email', 'max:190', Rule::unique('usuarios', 'correo_electronico')->ignore($id)],
            'trabajador_id' => ['nullable', Rule::exists('trabajadores', 'id'), Rule::unique('usuarios', 'trabajador_id')->ignore($id)],
            'rol' => ['required', Rule::in(Esquema::enum('usuarios', 'rol'))],
            'activo' => ['required', 'boolean'],
            'telefono' => [
                'bail', 'nullable', 'string', 'regex:/\A\+[1-9][0-9]{7,14}\z/',
                static function (string $atributo, string $valor, Closure $error): void {
                    if (str_starts_with($valor, '+51') && ! preg_match('/\A\+519[0-9]{8}\z/', $valor)) {
                        $error('Ingrese un celular peruano de 9 dígitos que empiece con 9.');
                    }
                },
            ],
        ];
        if ($id === null) {
            $reglas['password'] = ClaveMunichis::reglas();
        }

        return $request->validate($reglas, [
            ...ClaveMunichis::mensajes(),
            'telefono.regex' => 'Ingrese un celular peruano de 9 dígitos o un número internacional con código de país.',
        ]);
    }

    private function autorizarUsuario(Usuario $usuario): void
    {
        $this->autorizar(['ADMINISTRADOR']);
        abort_unless((int) $usuario->establecimiento_id === $this->establecimientoId(), 404);
    }

    private function audit(Usuario $usuario): array
    {
        return [
            'nombre' => $usuario->nombre,
            'correo_electronico' => $usuario->correo_electronico,
            'trabajador_id' => $usuario->trabajador_id,
            'rol' => $usuario->rol,
            'activo' => (bool) $usuario->activo,
        ];
    }
}
