<div class="campo-formulario">
    <label for="{{ $id }}">{{ $etiqueta }}</label>
    <div class="munichis-clave">
        <input id="{{ $id }}" name="{{ $nombre }}" type="password"
               autocomplete="{{ $nombre === 'current_password' ? 'current-password' : 'new-password' }}"
               @if ($nombre !== 'current_password') minlength="12" @endif
               @if ($nombre === 'password') aria-describedby="munichis-clave-reglas" data-munichis-clave-nueva @endif
               required>
        <button type="button" class="munichis-ojo" data-munichis-ver-clave aria-controls="{{ $id }}" aria-pressed="false"
                aria-label="Mostrar u ocultar {{ mb_strtolower($etiqueta) }}" title="Mostrar contraseña" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"/>
                <circle cx="12" cy="12" r="2.6"/>
                <path class="munichis-ojo-tachado" d="m3 3 18 18"/>
            </svg>
        </button>
    </div>
    @if ($nombre === 'password')
        <div class="munichis-medidor" data-munichis-medidor hidden>
            <div class="munichis-medidor-etiqueta">
                <label for="munichis-clave-nivel">Seguridad estimada</label>
                <span data-munichis-nivel-texto aria-live="polite">Sin evaluar</span>
            </div>
            <progress id="munichis-clave-nivel" value="0" max="4" aria-valuetext="Sin evaluar"></progress>
        </div>
        <small id="munichis-clave-reglas">{{ \App\Support\ClaveMunichis::AYUDA }}</small>
    @endif
</div>
