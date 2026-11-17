@extends('layouts.aplicacion')

@section('titulo','Control de caja')
@section('encabezado','Control de caja')

@section('contenido')
@php
    $rol = auth()->user()->rol;
    $puedeEditar = in_array($rol, ['ADMINISTRADOR','GERENCIA','SUPERVISOR'], true);
    $puedeEliminar = $rol === 'ADMINISTRADOR';

    $ultimoEsperado = $ultimo
        ? (data_get($ultimo,'efectivo_esperado') ?? data_get($ultimo,'efectivo_libro'))
        : null;
    $ultimoContado = $ultimo ? data_get($ultimo,'efectivo_contado') : null;
    $ultimaDiferencia = $ultimo ? data_get($ultimo,'diferencia') : null;
    $ultimoDigital = $ultimo ? data_get($ultimo,'digital_diario') : null;
@endphp

<div class="encabezado-pagina caja-listado-encabezado">
    <div>
        <span class="tarjeta-sobretitulo">Tesorería</span>
        <h1>Control de caja</h1>
    </div>

    @if($puedeEditar)
        <a href="{{ route('caja.crear') }}" class="boton boton-primario">
            <x-icono nombre="agregar" tamano="18"/> Nuevo control
        </a>
    @endif
</div>

@if(session('exito'))
    <div class="alerta alerta-exito">{{ session('exito') }}</div>
@endif

@if($ultimo)
<section class="caja-ultimo-grid">
    <article class="tarjeta caja-mini-card">
        <span>Último efectivo esperado</span>
        <strong>{{ $ultimoEsperado !== null ? 'S/ '.number_format((float)$ultimoEsperado,2) : '—' }}</strong>
    </article>

    <article class="tarjeta caja-mini-card">
        <span>Último efectivo contado</span>
        <strong>{{ $ultimoContado !== null ? 'S/ '.number_format((float)$ultimoContado,2) : '—' }}</strong>
    </article>

    <article class="tarjeta caja-mini-card caja-mini-digital">
        <span>Digital del último control</span>
        <strong>{{ $ultimoDigital !== null ? 'S/ '.number_format((float)$ultimoDigital,2) : '—' }}</strong>
    </article>

    @php
        $claseUltimaDif = $ultimaDiferencia === null
            ? ''
            : ((float)$ultimaDiferencia < 0
                ? 'caja-dif-roja'
                : ((float)$ultimaDiferencia > 0 ? 'caja-dif-azul' : 'caja-dif-negra'));
    @endphp
    <article class="tarjeta caja-mini-card {{ $claseUltimaDif }}">
        <span>Última diferencia</span>
        <strong>{{ $ultimaDiferencia !== null ? 'S/ '.number_format((float)$ultimaDiferencia,2) : '—' }}</strong>
    </article>
</section>
@endif

<section class="tarjeta">
    <div class="tarjeta-cabecera caja-historial-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Histórico</span>
            <h2>Controles diarios</h2>
        </div>

        <form method="GET" class="filtro-fechas-mini">
            <input type="date" name="desde" value="{{ $desde }}">
            <input type="date" name="hasta" value="{{ $hasta }}">
            <button class="boton boton-secundario boton-compacto">Aplicar</button>
        </form>
    </div>

    <div class="tabla-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th class="numero">Esperado</th>
                    <th class="numero">Contado</th>
                    <th class="numero">Otros ingresos</th>
                    <th class="numero">Depósitos</th>
                    <th class="numero">Digital</th>
                    <th class="numero">Diferencia</th>
                    <th>Estado</th>
                    @if($puedeEditar || $puedeEliminar)
                        <th class="numero gm-col-acciones">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody>
            @forelse($historial as $r)
                @php
                    $fechaValor = $fechaCol ? data_get($r,$fechaCol) : null;
                    $esperado = data_get($r,'efectivo_esperado') ?? data_get($r,'efectivo_libro');
                    $contado = data_get($r,'efectivo_contado');
                    $depositos = data_get($r,'depositos_banco',0);
                    $otrosIngresos = data_get($r,'otros_ingresos_caja',0);
                    $digital = data_get($r,'digital_diario');
                    $dif = data_get($r,'diferencia');

                    if ($dif === null) {
                        $estado = 'Pendiente';
                        $clase = '';
                    } elseif ((float)$dif < 0) {
                        $estado = 'Faltante';
                        $clase = 'caja-texto-rojo';
                    } elseif ((float)$dif > 0) {
                        $estado = 'Sobrante';
                        $clase = 'caja-texto-azul';
                    } else {
                        $estado = 'Caja conforme';
                        $clase = 'caja-texto-negro';
                    }
                @endphp
                <tr>
                    <td><strong>{{ $fechaValor ? \Carbon\Carbon::parse($fechaValor)->format('d/m/Y') : '—' }}</strong></td>
                    <td class="numero">{{ $esperado !== null ? 'S/ '.number_format((float)$esperado,2) : '—' }}</td>
                    <td class="numero">{{ $contado !== null ? 'S/ '.number_format((float)$contado,2) : '—' }}</td>
                    <td class="numero">{{ 'S/ '.number_format((float)$otrosIngresos,2) }}</td>
                    <td class="numero">{{ 'S/ '.number_format((float)$depositos,2) }}</td>
                    <td class="numero">{{ $digital !== null ? 'S/ '.number_format((float)$digital,2) : '—' }}</td>
                    <td class="numero {{ $clase }}">{{ $dif !== null ? 'S/ '.number_format((float)$dif,2) : '—' }}</td>
                    <td><span class="caja-estado {{ $clase }}">{{ $estado }}</span></td>
                    @if($puedeEditar || $puedeEliminar)
                        <td class="numero gm-celda-acciones">
                            <div class="acciones-tabla gm-acciones-caja">
                                @if($puedeEditar)
                                    <a
                                        href="{{ route('caja.crear',['fecha'=>$fechaValor]) }}"
                                        class="gm-boton-tabla gm-boton-editar"
                                        title="Editar control del {{ $fechaValor ? \Carbon\Carbon::parse($fechaValor)->format('d/m/Y') : '' }}"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4Zm2-3 9-9 1 1-9 9H6v-1Zm10-13 1.6-1.6a1.4 1.4 0 0 1 2 0l2 2a1.4 1.4 0 0 1 0 2L20 8l-4-4Z"/></svg>
                                        <span>Editar</span>
                                    </a>
                                @endif

                                @if($puedeEliminar)
                                    <button
                                        type="button"
                                        class="gm-boton-tabla gm-boton-eliminar"
                                        data-abrir-eliminar-caja
                                        data-url="{{ route('caja.eliminar', $r->id) }}"
                                        data-fecha="{{ $fechaValor ? \Carbon\Carbon::parse($fechaValor)->format('d/m/Y') : '—' }}"
                                        title="Eliminar control"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-2 6h10l-.7 11H7.7L7 9Zm3 2v7h2v-7h-2Zm4 0v7h2v-7h-2Z"/></svg>
                                        <span>Eliminar</span>
                                    </button>
                                @endif
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ ($puedeEditar || $puedeEliminar) ? 9 : 8 }}" class="texto-secundario">
                        No hay controles de caja en el periodo seleccionado.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($historial,'links'))
        <div style="margin-top:16px;">{{ $historial->links() }}</div>
    @endif
</section>

@push('estilos')
<style>
    .caja-listado-encabezado {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 18px;
    }

    .caja-ultimo-grid {
        display: grid;
        grid-template-columns: repeat(4,minmax(0,1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .caja-mini-card {
        padding: 16px 18px;
    }

    .caja-mini-card span {
        display: block;
        color: #64748b;
        font-size: .78rem;
        font-weight: 700;
    }

    .caja-mini-card strong {
        display: block;
        margin-top: 5px;
        color: #0f172a;
        font-size: 1.3rem;
        font-variant-numeric: tabular-nums;
    }

    .caja-mini-card small {
        color: #1d4ed8;
        font-weight: 700;
    }

    .caja-mini-digital {
        background: #f8fbff;
    }

    .caja-dif-roja strong,
    .caja-texto-rojo {
        color: #b91c1c !important;
    }

    .caja-dif-azul strong,
    .caja-texto-azul {
        color: #1d4ed8 !important;
    }

    .caja-dif-negra strong,
    .caja-texto-negro {
        color: #111827 !important;
    }

    .caja-estado {
        font-weight: 800;
        white-space: nowrap;
    }

    .caja-historial-cabecera {
        gap: 20px;
    }

    @media (max-width: 950px) {
        .caja-ultimo-grid {
            grid-template-columns: repeat(2,minmax(0,1fr));
        }
    }

    @media (max-width: 650px) {
        .caja-listado-encabezado,
        .caja-historial-cabecera {
            align-items: stretch;
            flex-direction: column;
        }

        .caja-ultimo-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

{{-- AJUSTE_UI_V2_1_CAJA_MODAL --}}
@include('caja.partials.eliminacion-admin')

@endsection
