@php($tipo = $tipo ?? 'reporte')
<svg class="gm-reporte-icono-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($tipo)
        @case('ventas')
            <path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/><path d="m3 6 5-3 5 4 7-4"/>
            @break
        @case('galones')
            <path d="M7 3h8v18H7z"/><path d="M9 7h4M15 8h2l3 3v6a2 2 0 0 1-4 0v-4"/><path d="M9 16h4"/>
            @break
        @case('caja')
            <path d="M3 7h18v12H3z"/><path d="M3 10h18M7 15h4"/><circle cx="17" cy="15" r="1.5"/>
            @break
        @case('creditos')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>
            @break
        @case('descuentos')
            <path d="m4 12 8-8h7v7l-8 8L4 12Z"/><circle cx="15.5" cy="7.5" r="1"/><path d="m8 15 7-7"/>
            @break
        @case('gastos')
            <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h4"/>
            @break
        @case('compras')
            <path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>
            @break
        @case('tanques')
            <ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v14c0 1.7 3.1 3 7 3s7-1.3 7-3V5"/><path d="M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3"/>
            @break
        @case('precios')
            <path d="M4 18V9M10 18V5M16 18v-7M22 18H2"/><path d="m3 7 6-4 5 5 7-4"/>
            @break
        @case('competencia')
            <path d="M4 20V10h6v10M14 20V4h6v16"/><path d="M2 20h20M6 7h2M16 8h2M16 12h2"/>
            @break
        @case('trabajadores')
            <circle cx="9" cy="8" r="3"/><path d="M3 20c.5-4 2.5-6 6-6s5.5 2 6 6"/><circle cx="17" cy="9" r="2"/><path d="M15 15c3.5-.5 5.5 1 6 5"/>
            @break
        @case('extraordinarios')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/><path d="M19 5 21 3"/>
            @break
        @case('conciliacion')
            <path d="M5 4h14v16H5z"/><path d="m8 10 2 2 5-5M8 16h8"/>
            @break
        @default
            <path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h5"/>
    @endswitch
</svg>
