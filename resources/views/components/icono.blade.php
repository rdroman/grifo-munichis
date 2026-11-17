@props(['nombre', 'tamano' => 20])

@php
    $atributos = $attributes->merge([
        'class' => 'icono',
        'width' => $tamano,
        'height' => $tamano,
        'viewBox' => '0 0 24 24',
        'fill' => 'none',
        'stroke' => 'currentColor',
        'stroke-width' => '1.8',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
        'aria-hidden' => 'true',
    ]);
@endphp

<svg {{ $atributos }}>
    @switch($nombre)
        @case('panel')
            <rect x="3" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="3" width="7" height="7" rx="1" />
            <rect x="3" y="14" width="7" height="7" rx="1" />
            <rect x="14" y="14" width="7" height="7" rx="1" />
            @break
        @case('movimientos')
            <path d="M4 7h13" /><path d="m14 4 3 3-3 3" />
            <path d="M20 17H7" /><path d="m10 14-3 3 3 3" />
            @break
        @case('turnos')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break
        @case('compras')
            <path d="M3 5h2l2.2 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L20 8H7" />
            <circle cx="10" cy="20" r="1" /><circle cx="17" cy="20" r="1" />
            @break
        @case('creditos')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="M3 10h18" /><path d="M7 15h3" />
            @break
        @case('gastos')
            <path d="M12 3v18" /><path d="M17 7.5c0-1.4-1.8-2.5-4-2.5s-4 1.1-4 2.5 1.8 2.5 4 2.5 4 1.1 4 2.5-1.8 2.5-4 2.5-4-1.1-4-2.5" />
            @break
        @case('tanques')
            <ellipse cx="12" cy="5" rx="7" ry="3" />
            <path d="M5 5v14c0 1.7 3.1 3 7 3s7-1.3 7-3V5" />
            <path d="M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3" />
            @break
        @case('trabajadores')
            <circle cx="9" cy="8" r="3" />
            <path d="M3 20c.6-4 2.6-6 6-6s5.4 2 6 6" />
            <circle cx="17" cy="9" r="2" /><path d="M16 15c2.9 0 4.5 1.6 5 5" />
            @break
        @case('clientes')
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21c.8-5 3.4-7 8-7s7.2 2 8 7" />
            @break
        @case('precios')
            <path d="M20 13 13 20 4 11V4h7z" />
            <circle cx="8.5" cy="8.5" r="1.2" />
            @break
        @case('competencia')
            <path d="M4 20V10l4-3 4 3v10" /><path d="M12 20V6l4-3 4 3v14" />
            <path d="M7 13h2M7 16h2M15 9h2M15 12h2M15 15h2" />
            @break
        @case('reportes')
            <path d="M5 21V10" /><path d="M12 21V4" /><path d="M19 21v-7" />
            @break
        @case('configuracion')
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z" />
            @break
        @case('auditoria')
            <path d="M12 3 5 6v5c0 4.5 2.8 8 7 10 4.2-2 7-5.5 7-10V6z" />
            <path d="m9 12 2 2 4-4" />
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('cerrar')
            <path d="M10 4H5v16h5" /><path d="M14 8l4 4-4 4" /><path d="M18 12H9" />
            @break
        @case('usuario')
            <circle cx="12" cy="8" r="4" /><path d="M4 21c.8-5 3.4-7 8-7s7.2 2 8 7" />
            @break
        @case('chevron')
            <path d="m9 18 6-6-6-6" />
            @break
        @case('alerta')
            <path d="M12 3 2.8 20h18.4z" /><path d="M12 9v4" /><path d="M12 17h.01" />
            @break
        @case('check')
            <circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" />
            @break
        @case('dinero')
            <rect x="3" y="6" width="18" height="12" rx="2" /><circle cx="12" cy="12" r="3" /><path d="M7 9H6v1M17 15h1v-1" />
            @break
        @case('combustible')
            <path d="M6 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16" /><path d="M5 21h12" /><path d="M8 6h6v5H8z" /><path d="M16 8h2l2 2v6a2 2 0 0 1-2 2h-2" />
            @break
        @case('calendario')
            <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4M16 3v4M3 10h18" />
            @break
        @case('agregar')
            <circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" />
            @break
        @case('editar')
            <path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z" />
            @break
        @case('buscar')
            <circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />
            @break
        @case('activar')
            <circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" />
            @break
        @case('desactivar')
            <circle cx="12" cy="12" r="9" /><path d="M8 12h8" />
            @break
        @case('guardar')
            <path d="M5 3h12l2 2v16H5z" /><path d="M8 3v6h8V3M8 21v-7h8v7" />
            @break
        @case('volver')
            <path d="m15 18-6-6 6-6" /><path d="M9 12h10" />
            @break
        @default
            <circle cx="12" cy="12" r="9" />
    @endswitch
</svg>
