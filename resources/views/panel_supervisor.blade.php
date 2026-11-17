@extends('layouts.aplicacion')

@section('titulo', 'Panel de supervisión')
@section('encabezado', 'Panel de supervisión')

@section('contenido')
@php
    $fechaTexto = $fechaReferencia
        ? \Carbon\Carbon::parse($fechaReferencia)->format('d/m/Y')
        : 'Sin datos';

    $rangoDia = $fechaReferencia
        ? ['desde' => $fechaReferencia, 'hasta' => $fechaReferencia]
        : [];

    $urlVentas = $fechaReferencia
        ? route('reportes.detalle', array_merge(['tipo'=>'ventas'], $rangoDia))
        : route('reportes.indice');

    $urlGalones = $fechaReferencia
        ? route('reportes.detalle', array_merge(['tipo'=>'galones'], $rangoDia))
        : route('reportes.indice');
@endphp

<div class="encabezado-pagina gm-encabezado-directo">
    <div>
        <span class="tarjeta-sobretitulo">Operación</span>
        <h1>Panel de supervisión</h1>
    </div>
    <div class="estado-datos actual">
        <span class="punto-estado"></span>
        {{ $fechaTexto }}
    </div>
</div>

<section class="rejilla-metricas gm-metricas-clicables">
    <a class="tarjeta-metrica gm-card-link" href="{{ $urlVentas }}">
        <div class="metrica-icono verde">
            <x-icono nombre="dinero" tamano="22"/>
        </div>
        <div>
            <span class="metrica-etiqueta">Ventas</span>
            <strong class="metrica-valor">S/ {{ number_format($ventasDia, 2) }}</strong>
        </div>
    </a>

    <a class="tarjeta-metrica gm-card-link gm-card-galones" href="{{ $urlGalones }}">
        <div class="metrica-icono amarillo">
            <x-icono nombre="combustible" tamano="22"/>
        </div>
        <div>
            <span class="metrica-etiqueta">Galones despachados</span>
            <strong class="metrica-valor">{{ number_format($galonesDia, 3) }} gal</strong>
            <div class="gm-mini-desglose">
                @foreach($galonesPorProducto as $producto)
                    <span><b>{{ $producto->codigo }}</b> {{ number_format((float) $producto->galones, 3) }}</span>
                @endforeach
            </div>
        </div>
    </a>

    <a class="tarjeta-metrica gm-card-link" href="{{ route('turnos.indice') }}">
        <div class="metrica-icono azul">
            <x-icono nombre="turnos" tamano="22"/>
        </div>
        <div>
            <span class="metrica-etiqueta">Último turno</span>
            @php $ultimo = $ultimosTurnos->first(); @endphp
            <strong class="metrica-valor">{{ $ultimo?->codigo_turno ?? '—' }}</strong>
            <span class="gm-metrica-contexto">{{ $ultimo?->trabajador ?? 'Sin registro' }}</span>
        </div>
    </a>

    <a class="tarjeta-metrica gm-card-link" href="{{ route('tanques.indice') }}">
        <div class="metrica-icono verde">
            <x-icono nombre="combustible" tamano="22"/>
        </div>
        <div>
            <span class="metrica-etiqueta">Tanques</span>
            <strong class="metrica-valor">{{ $tanques->count() }}</strong>
        </div>
    </a>
</section>

<div class="gm-dashboard-supervisor-grid">
    <section class="tarjeta tabla-tarjeta gm-tarjeta-compacta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Operación reciente</span>
                <h2>Últimos turnos</h2>
            </div>
            <a href="{{ route('turnos.indice') }}" class="enlace-secundario">
                Ver todos
                <x-icono nombre="chevron" tamano="16"/>
            </a>
        </div>

        <div class="tabla-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Turno</th>
                        <th>Trabajador</th>
                        <th class="numero">Venta</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ultimosTurnos as $turno)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($turno->fecha_operacion)->format('d/m/Y') }}</td>
                            <td><span class="insignia turno">{{ $turno->codigo_turno }}</span></td>
                            <td>{{ $turno->trabajador ?? '—' }}</td>
                            <td class="numero">S/ {{ number_format((float) $turno->ventas_brutas_combustible, 2) }}</td>
                            <td>
                                <a class="boton-accion" href="{{ route('turnos.mostrar', $turno->turno_operativo_id) }}" title="Ver turno">
                                    <x-icono nombre="chevron" tamano="17"/>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tabla-vacia">No existen turnos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta gm-tarjeta-compacta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Inventario</span>
                <h2>Tanques</h2>
            </div>
        </div>

        <div class="gm-lista-tanques-supervisor">
            @forelse($tanques as $tanque)
                <div class="gm-tanque-fila">
                    <div>
                        <strong>{{ $tanque->codigo }}</strong>
                        <span>{{ $tanque->nombre }}</span>
                    </div>
                    <div class="numero">
                        <strong>{{ $tanque->saldo_medido === null ? '—' : number_format($tanque->saldo_medido, 3).' gal' }}</strong>
                        @if($tanque->fecha_control)
                            <small>{{ \Carbon\Carbon::parse($tanque->fecha_control)->format('d/m/Y') }}</small>
                        @endif
                    </div>
                </div>
            @empty
                <p class="tabla-vacia">No hay tanques configurados.</p>
            @endforelse
        </div>
    </section>
</div>

@endsection
