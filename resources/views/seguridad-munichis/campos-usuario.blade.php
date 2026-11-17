<div class="campo-formulario">
    <label for="telefono-usuario">Celular</label>
    <input id="telefono-usuario" type="tel" name="telefono"
           autocomplete="tel" maxlength="30" placeholder="Ej. 987 654 321"
           value="{{ old('telefono', $usuario->telefono) }}">
</div>
@if (! $esEdicion)
    <div class="campo-formulario">
        <label for="clave-inicial">Contraseña inicial *</label>
        <input id="clave-inicial" type="password" name="password" minlength="12" autocomplete="new-password" required>
        <small>{{ \App\Support\ClaveMunichis::AYUDA }}</small>
    </div>
    <div class="campo-formulario">
        <label for="confirmar-clave-inicial">Confirmar contraseña *</label>
        <input id="confirmar-clave-inicial" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
    </div>
@endif
