@extends('layouts.aplicacion')

@section('titulo', 'Reportes')
@section('encabezado', 'Reportes')

@section('contenido')

@php
    $gruposReportes = [
        'operacion' => [
            'titulo' => 'Ventas y operación',
            'descripcion' => 'Despacho, ventas, personal y cortes operativos.',
            'claves' => ['ventas','galones','trabajadores','extraordinarios'],
        ],
        'finanzas' => [
            'titulo' => 'Caja y finanzas',
            'descripcion' => 'Caja diaria, créditos, descuentos y gastos.',
            'claves' => ['caja','creditos','descuentos','gastos'],
        ],
        'inventario' => [
            'titulo' => 'Inventario y combustible',
            'descripcion' => 'Abastecimiento, existencias y conciliación física.',
            'claves' => ['compras','tanques','conciliacion'],
        ],
        'mercado' => [
            'titulo' => 'Precios y mercado',
            'descripcion' => 'Vigencia de precios y comparación con la competencia.',
            'claves' => ['precios','competencia'],
        ],
    ];
    $totalGalones = (float) $galonesPorProducto->sum('galones');
@endphp

<div class="encabezado-pagina gm-encabezado-directo gm-reportes-encabezado">
    <div>
        <span class="tarjeta-sobretitulo">Análisis</span>
        <h1>Reportes</h1>
        <p class="texto-secundario">Consulta el período y entra al detalle del indicador que necesites.</p>
    </div>

    <form method="GET" action="{{ route('reportes.indice') }}" class="gm-filtro-fechas-cabecera gm-filtro-reportes">
        <div class="campo-formulario">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $desde }}">
        </div>
        <div class="campo-formulario">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}">
        </div>
        <button class="boton boton-primario boton-compacto" type="submit">Aplicar</button>
    </form>
</div>

@if($galonesPorProducto->isNotEmpty())
<section class="tarjeta gm-tarjeta-compacta gm-resumen-combustibles gm-resumen-combustibles--nuevo">
    <div class="tarjeta-cabecera gm-reportes-seccion-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Despacho del período</span>
            <h2>Galones por combustible</h2>
        </div>
        <a
            class="gm-enlace-detalle-superior"
            href="{{ route('reportes.detalle', ['tipo'=>'galones','desde'=>$desde,'hasta'=>$hasta]) }}"
        >
            Ver detalle <span aria-hidden="true">→</span>
        </a>
    </div>

    <div class="gm-productos-resumen gm-productos-resumen--color">
        @foreach($galonesPorProducto as $producto)
            @php
                $codigoClase = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $producto->codigo));
                $porcentaje = $totalGalones > 0 ? ((float) $producto->galones / $totalGalones) * 100 : 0;
            @endphp
            <a
                href="{{ route('reportes.detalle', ['tipo'=>'galones','desde'=>$desde,'hasta'=>$hasta]) }}"
                class="gm-producto-chip gm-producto-chip--{{ $codigoClase }}"
            >
                <div class="gm-producto-chip-identidad">
                    <span class="gm-producto-codigo">{{ $producto->codigo }}</span>
                    <strong>{{ $producto->nombre }}</strong>
                </div>
                <div class="gm-producto-chip-metrica">
                    <b>{{ number_format((float) $producto->galones, 3) }} gal</b>
                    <span>{{ number_format($porcentaje, 1) }}% del despacho</span>
                </div>
                <span class="gm-producto-chip-barra" aria-hidden="true"><i style="width:{{ min(100, max(0, $porcentaje)) }}%"></i></span>
            </a>
        @endforeach
    </div>
</section>
@endif

<div class="gm-reportes-grupos">
    @foreach($gruposReportes as $claveGrupo => $grupo)
        @php($items = $reportes->whereIn('clave', $grupo['claves']))
        @if($items->isNotEmpty())
            <section class="gm-grupo-reportes gm-grupo-reportes--{{ $claveGrupo }}">
                <div class="gm-grupo-reportes-cabecera">
                    <span class="gm-grupo-marca" aria-hidden="true"></span>
                    <div>
                        <h2>{{ $grupo['titulo'] }}</h2>
                        <p>{{ $grupo['descripcion'] }}</p>
                    </div>
                </div>

                <div class="gm-grid-reportes">
                    @foreach($items as $reporte)
                        <a
                            class="tarjeta gm-reporte-card gm-reporte-card--{{ $reporte['clave'] }}"
                            href="{{ route('reportes.detalle', ['tipo'=>$reporte['clave'],'desde'=>$desde,'hasta'=>$hasta]) }}"
                        >
                            <div class="gm-reporte-card-superior">
                                <span class="gm-reporte-icono">
                                    @include('reportes.mejorados._icono', ['tipo'=>$reporte['clave']])
                                </span>
                                <span class="gm-reporte-abrir" aria-hidden="true">→</span>
                            </div>

                            <div class="gm-reporte-card-cuerpo">
                                <h3>{{ $reporte['titulo'] }}</h3>
                                <p>{{ $reporte['descripcion'] }}</p>
                            </div>

                            <div class="gm-reporte-card-pie">
                                <strong class="gm-reporte-valor">{{ $reporte['valor'] }}</strong>
                                <span class="gm-ver-detalle">Ver detalle</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</div>

@endsection
