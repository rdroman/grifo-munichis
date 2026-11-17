@if ($esEdicion && $usuario->id !== auth()->id())
    <details class="tarjeta" style="margin-top: 1rem" @if($errors->restablecerClave->any()) open @endif>
        <summary>Restablecer contraseña</summary>
        @if ($errors->restablecerClave->any())
            <div class="alerta alerta-error" role="alert">{{ $errors->restablecerClave->first() }}</div>
        @endif
        <p class="texto-secundario">El usuario tendrá que volver a iniciar sesión con la nueva contraseña.</p>
        <form class="formulario-modulo" method="POST" action="{{ route('seguridad-munichis.restablecer', $usuario) }}">
            @csrf
            <div class="rejilla-campos">
                <div class="campo-formulario">
                    <label for="clave-admin">Su contraseña de administrador</label>
                    <input id="clave-admin" type="password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="campo-formulario">
                    <label for="clave-restablecida">Nueva contraseña del usuario</label>
                    <input id="clave-restablecida" type="password" name="password" autocomplete="new-password" minlength="12" required>
                    <small>{{ \App\Support\ClaveMunichis::AYUDA }}</small>
                </div>
                <div class="campo-formulario">
                    <label for="confirmar-clave-restablecida">Confirmar nueva contraseña</label>
                    <input id="confirmar-clave-restablecida" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required>
                </div>
            </div>
            <div class="acciones-formulario">
                <button type="submit" class="boton boton-primario">Restablecer contraseña</button>
            </div>
        </form>
    </details>
@endif
