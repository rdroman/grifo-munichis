@extends('layouts.aplicacion')

@section('titulo', $titulo)
@section('encabezado', $titulo)

@section('contenido')

@php
    $descripcionesReporte = [
        'ventas' => 'Ventas registradas por fecha, turno y trabajador.',
        'galones' => 'Galones despachados por combustible, fecha y turno.',
        'caja' => 'Efectivo esperado, contado, depósitos y diferencias del control diario.',
        'creditos' => 'Operaciones a crédito registradas por cliente y turno.',
        'descuentos' => 'Despachos con descuento registrados por producto y turno.',
        'gastos' => 'Gastos registrados en el período seleccionado.',
        'compras' => 'Ingresos de combustible provenientes de compras.',
        'tanques' => 'Mediciones físicas, saldos teóricos y diferencias por tanque.',
        'precios' => 'Cambios de precio y vigencia por producto.',
        'competencia' => 'Precios registrados de grifos competidores.',
        'trabajadores' => 'Resumen descriptivo de turnos, galones y ventas por trabajador.',
        'extraordinarios' => 'Cortes T4, T5, T6 y otros cortes extraordinarios registrados.',
        'conciliacion' => 'Comparación de saldos teóricos y físicos de combustible.',
    ];
    $descripcionVisible = $descripcion ?? ($descripcionesReporte[$tipo] ?? null);
@endphp

<div class="encabezado-pagina gm-encabezado-directo gm-reporte-detalle-encabezado gm-reporte-card--{{ $tipo }}">
    <div>
        <a href="{{ route('reportes.indice', ['desde'=>$desde,'hasta'=>$hasta]) }}" class="enlace-volver">
            <x-icono nombre="volver" tamano="17"/> Reportes
        </a>
        <div class="gm-reporte-detalle-titulo">
            <span class="gm-reporte-icono gm-reporte-icono--grande">
                @include('reportes.mejorados._icono', ['tipo'=>$tipo])
            </span>
            <div>
                <h1>{{ $titulo }}</h1>
                @if($descripcionVisible)
                    <p class="texto-secundario gm-descripcion-reporte">{{ $descripcionVisible }}</p>
                @endif
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('reportes.detalle', ['tipo'=>$tipo]) }}" class="gm-filtro-fechas-cabecera gm-filtro-reportes">
        <div class="campo-formulario">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $desde }}">
        </div>
        <div class="campo-formulario">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}">
        </div>
        <input type="hidden" name="filas" value="{{ $filasPorPagina }}">
        <button class="boton boton-primario boton-compacto">Aplicar</button>
    </form>
</div>

@if($tipo === 'galones' && $galonesPorProducto->isNotEmpty())
<section class="gm-productos-resumen gm-productos-detalle gm-productos-resumen--color">
    @php $totalGalones = (float) $galonesPorProducto->sum('galones'); @endphp

    @foreach($galonesPorProducto as $producto)
        @php
            $porcentaje = $totalGalones > 0 ? ((float) $producto->galones / $totalGalones) * 100 : 0;
            $codigoClase = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $producto->codigo));
        @endphp
        <article class="tarjeta gm-producto-resumen-card gm-producto-chip--{{ $codigoClase }}">
            <div>
                <span class="gm-producto-codigo">{{ $producto->codigo }}</span>
                <strong>{{ $producto->nombre }}</strong>
            </div>
            <div class="numero">
                <strong>{{ number_format((float) $producto->galones, 3) }} gal</strong>
                <small>{{ number_format($porcentaje, 1) }}%</small>
            </div>
        </article>
    @endforeach
</section>
@endif


<section class="gm-graficos-wrapper" id="gm-graficos-interactivos" aria-labelledby="gm-graficos-titulo">
    <div class="gm-graficos-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Análisis del período</span>
            <h2 id="gm-graficos-titulo">Gráficos interactivos</h2>
            <p>Todos los filtros comienzan en «Todos». Cambia los indicadores para analizar las tendencias.</p>
        </div>
        <span class="gm-grafico-sello">Sin alterar registros</span>
    </div>
    <div class="gm-grafico-filtros" id="gm-grafico-filtros" aria-label="Filtros del gráfico"></div>
    <div class="gm-grafico-paneles" id="gm-grafico-paneles" aria-live="polite"></div>
    <p class="gm-grafico-nota" id="gm-grafico-aviso"></p>
    <noscript><p class="gm-sin-datos">Habilita JavaScript en el navegador para ver los gráficos; la tabla de detalle sigue disponible.</p></noscript>
</section>
<script type="application/json" id="gm-datos-graficos">{!! json_encode($configuracionGrafica, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) !!}</script>

@push('estilos')
<link rel="stylesheet" href="{{ asset('css/gm-graficos-reportes-v1.css') }}?v=1.0.0">
<link rel="stylesheet" href="{{ asset('css/gm-paginacion-reportes-v1.css') }}?v=1.1.0">
@endpush
@push('scripts')
<script src="{{ asset('js/gm-graficos-reportes-v1.js') }}?v=1.0.0" defer></script>
@endpush

@php
    $totalColumnas = count($columnas) + ($tieneTurno ? 1 : 0);
    $paginaActual = $filas->currentPage();
    $ultimaPagina = $filas->lastPage();
    $paginasVisibles = collect([1, $ultimaPagina])
        ->merge(range(max(1, $paginaActual - 2), min($ultimaPagina, $paginaActual + 2)))
        ->unique()->sort()->values();
@endphp

<section class="tarjeta tabla-tarjeta gm-tarjeta-compacta gm-tabla-reporte" id="detalle-registros">
    <div class="tarjeta-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Detalle del período</span>
            <h2>{{ number_format($filas->total()) }} registro{{ $filas->total() === 1 ? '' : 's' }}</h2>
            <p class="gm-paginacion-orden">Orden: registros más recientes primero</p>
        </div>
        <form method="GET" action="{{ route('reportes.detalle', ['tipo' => $tipo]) }}#detalle-registros" class="gm-paginacion-tamano">
            <input type="hidden" name="desde" value="{{ $desde }}">
            <input type="hidden" name="hasta" value="{{ $hasta }}">
            <label for="gm-filas-por-pagina">Filas por página</label>
            <select name="filas" id="gm-filas-por-pagina" onchange="this.form.requestSubmit()">
                @foreach([10, 20, 50, 100] as $opcion)
                    <option value="{{ $opcion }}" @selected($filasPorPagina === $opcion)>{{ $opcion }}</option>
                @endforeach
            </select>
            <button type="submit" class="gm-paginacion-aplicar">Ver</button>
        </form>
    </div>

    <div class="tabla-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    @foreach($columnas as $campo => $etiqueta)
                        <th class="{{ in_array($campo, $moneda ?? [], true) || isset(($decimales ?? [])[$campo]) ? 'numero' : '' }}">
                            {{ $etiqueta }}
                        </th>
                    @endforeach
                    @if($tieneTurno)
                        <th></th>
                    @endif
                </tr>
            </thead>

            <tbody>
                @forelse($filas as $fila)
                    <tr>
                        @foreach($columnas as $campo => $etiqueta)
                            @php $valor = data_get($fila, $campo); @endphp
                            <td class="{{ in_array($campo, $moneda ?? [], true) || isset(($decimales ?? [])[$campo]) ? 'numero' : '' }}">
                                @if(in_array($campo, $moneda ?? [], true))
                                    S/ {{ number_format((float) $valor, 2) }}
                                @elseif(isset(($decimales ?? [])[$campo]))
                                    {{ number_format((float) $valor, (int) $decimales[$campo]) }}
                                @elseif(str_contains($campo, 'fecha') && $valor)
                                    {{ \Carbon\Carbon::parse($valor)->format(str_contains((string) $valor, ':') ? 'd/m/Y H:i' : 'd/m/Y') }}
                                @else
                                    {{ $valor === null || $valor === '' ? '—' : $valor }}
                                @endif
                            </td>
                        @endforeach

                        @if($tieneTurno)
                            <td>
                                @if(data_get($fila, 'turno_operativo_id'))
                                    <a
                                        class="boton-accion"
                                        href="{{ route('turnos.mostrar', data_get($fila, 'turno_operativo_id')) }}"
                                        title="Ver turno"
                                    >
                                        <x-icono nombre="chevron" tamano="17"/>
                                    </a>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(1, $totalColumnas) }}" class="tabla-vacia">
                            No hay registros en el período seleccionado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="gm-paginacion-pie">
        <p class="gm-paginacion-conteo" aria-live="polite">
            Mostrando <strong>{{ $filas->firstItem() ?? 0 }}–{{ $filas->lastItem() ?? 0 }}</strong>
            de <strong>{{ number_format($filas->total()) }}</strong> registros
        </p>
        @if($filas->hasPages())
            <nav class="gm-paginacion-enlaces" aria-label="Páginas del reporte">
                @if($filas->onFirstPage())
                    <span class="gm-pagina gm-pagina--inactiva" aria-disabled="true">Anterior</span>
                @else
                    <a class="gm-pagina" href="{{ $filas->previousPageUrl() }}" rel="prev">Anterior</a>
                @endif
                @php $paginaAnteriorVisible = 0; @endphp
                @foreach($paginasVisibles as $numeroPagina)
                    @if($numeroPagina > $paginaAnteriorVisible + 1)
                        <span class="gm-pagina gm-pagina--puntos" aria-hidden="true">…</span>
                    @endif
                    @if($numeroPagina === $paginaActual)
                        <span class="gm-pagina gm-pagina--actual" aria-current="page">{{ $numeroPagina }}</span>
                    @else
                        <a class="gm-pagina" href="{{ $filas->url($numeroPagina) }}" aria-label="Ir a la página {{ $numeroPagina }}">{{ $numeroPagina }}</a>
                    @endif
                    @php $paginaAnteriorVisible = $numeroPagina; @endphp
                @endforeach
                @if($filas->hasMorePages())
                    <a class="gm-pagina" href="{{ $filas->nextPageUrl() }}" rel="next">Siguiente</a>
                @else
                    <span class="gm-pagina gm-pagina--inactiva" aria-disabled="true">Siguiente</span>
                @endif
            </nav>
        @endif
    </div>
</section>

@endsection
