<a
    class="boton-accion peligro"
    href="{{ route('gastos.confirmar-eliminacion', ['id' => $registro->id] + request()->only(['q', 'desde', 'hasta', 'page'])) }}"
    title="Eliminar gasto"
    aria-label="Eliminar gasto {{ $registro->id }}"
>
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m5 4v6m4-6v6"/>
    </svg>
</a>
