@if(auth()->check() && auth()->user()->rol === 'ADMINISTRADOR')
<dialog id="modal-eliminar-control-caja" class="modal-simple gm-modal-eliminar-caja" aria-labelledby="titulo-eliminar-control-caja">
    <form method="POST" id="form-eliminar-control-caja">
        @csrf
        @method('DELETE')

        <div class="modal-cabecera gm-modal-caja-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Administración</span>
                <h2 id="titulo-eliminar-control-caja">Eliminar control de caja</h2>
            </div>
            <button type="button" class="gm-modal-cerrar" data-cerrar-eliminar-caja aria-label="Cerrar">×</button>
        </div>

        <div class="gm-modal-aviso">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1 21h22L12 2Zm0 5 6.9 12H5.1L12 7Zm-1 3v5h2v-5h-2Zm0 7v2h2v-2h-2Z"/></svg>
            <div>
                <strong>Control del <span data-fecha-eliminar-caja>—</span></strong>
                <p>El registro quedará archivado y, si existen controles posteriores, se recalculará la cadena de efectivo.</p>
            </div>
        </div>

        <div class="campo-formulario gm-campo-motivo-eliminar">
            <label for="motivo_eliminacion">Motivo breve *</label>
            <input
                id="motivo_eliminacion"
                type="text"
                name="motivo_eliminacion"
                minlength="5"
                maxlength="250"
                required
                autocomplete="off"
                placeholder="Ej.: registro duplicado o fecha incorrecta"
            >
        </div>

        <div class="gm-modal-acciones">
            <button type="button" class="boton boton-secundario" data-cerrar-eliminar-caja>Cancelar</button>
            <button type="submit" class="boton gm-boton-confirmar-eliminar">Eliminar control</button>
        </div>
    </form>
</dialog>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal-eliminar-control-caja');
    const formulario = document.getElementById('form-eliminar-control-caja');
    const fecha = modal?.querySelector('[data-fecha-eliminar-caja]');
    const motivo = document.getElementById('motivo_eliminacion');

    if (!modal || !formulario) return;

    document.querySelectorAll('[data-abrir-eliminar-caja]').forEach((boton) => {
        boton.addEventListener('click', () => {
            formulario.action = boton.dataset.url || '';
            if (fecha) fecha.textContent = boton.dataset.fecha || '—';
            if (motivo) motivo.value = '';
            modal.showModal();
            window.setTimeout(() => motivo?.focus(), 30);
        });
    });

    modal.querySelectorAll('[data-cerrar-eliminar-caja]').forEach((boton) => {
        boton.addEventListener('click', () => modal.close());
    });

    modal.addEventListener('click', (evento) => {
        if (evento.target === modal) modal.close();
    });
});
</script>
@endpush
@endif
