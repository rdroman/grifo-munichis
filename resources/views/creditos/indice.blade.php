@extends('layouts.aplicacion')
@section('titulo','Créditos')
@section('encabezado','Créditos')
@section('contenido')
<div class="encabezado-pagina encabezado-pagina-acciones">
    <div>
        <span class="tarjeta-sobretitulo">Administración</span>
        <h1>{{ $esOperador ? 'Créditos de mis turnos' : 'Créditos y vales' }}</h1>
        <p class="texto-secundario">{{ $esOperador ? 'Solo puedes consultar y registrar ventas a crédito realizadas en tus turnos.' : 'Ventas a crédito, saldos de clientes y pagos.' }}</p>
    </div>
    @if(in_array(auth()->user()->rol,['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR','CONTABILIDAD'],true))
        <a class="boton boton-primario" href="{{ route('creditos.crear') }}"><x-icono nombre="agregar" tamano="18"/> Nueva venta</a>
    @endif
</div>
<section class="tarjeta filtros-tarjeta"><form method="GET" class="filtros-formulario filtros-extendidos"><div class="campo-formulario"><label>Desde</label><input type="date" name="desde" value="{{ $desde }}"></div><div class="campo-formulario"><label>Hasta</label><input type="date" name="hasta" value="{{ $hasta }}"></div><div class="campo-formulario"><label>Cliente</label><select name="cliente"><option value="">Todos</option>@foreach($clientes as $cl)<option value="{{ $cl->id }}" @selected((string)$cliente===(string)$cl->id)>{{ $cl->nombre }}</option>@endforeach</select></div><div class="filtros-acciones"><button class="boton boton-primario boton-compacto">Filtrar</button></div></form></section>
<div class="{{ $esOperador ? '' : 'rejilla-dos-columnas credito-rejilla' }}">
<section class="tarjeta tabla-tarjeta">
    <div class="tarjeta-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Ventas</span>
            <h2>Vales registrados</h2>
        </div>
    </div>

    <div class="tabla-responsive">
        <table class="tabla-datos tabla-acciones">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vale</th>
                    <th class="numero">Total</th>
                    <th class="numero">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                    <tr>
                        <td>{{ $c['fecha_venta'] && $v->{$c['fecha_venta']} ? \Carbon\Carbon::parse($v->{$c['fecha_venta']})->format('d/m/Y') : '—' }}</td>
                        <td>{{ $v->cliente }}</td>
                        <td>{{ $c['numero_vale'] ? ($v->{$c['numero_vale']} ?: '—') : '—' }}</td>
                        <td class="numero">S/ {{ number_format($totales[$v->id] ?? 0, 2) }}</td>
                        <td class="numero" data-etiqueta="Acciones">
                            <div style="display:flex; gap:8px; justify-content:flex-end; align-items:center;">
                                <a class="boton-accion" href="{{ route('creditos.mostrar', $v->id) }}" title="Ver detalle">
                                    <x-icono nombre="chevron" tamano="17"/>
                                </a>
                                @if(auth()->user()?->rol === 'ADMINISTRADOR')
                                    <a class="boton-accion" href="{{ route('creditos.editar', $v->id) }}" title="Editar crédito">
                                        <x-icono nombre="editar" tamano="17"/>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabla-vacia">No hay ventas a crédito.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($ventas->hasPages())
        <div class="paginacion-simple">
            <div>
                Mostrando {{ $ventas->firstItem() }}–{{ $ventas->lastItem() }} de {{ $ventas->total() }}
            </div>
            <div class="paginacion-acciones">
                @if(!$ventas->onFirstPage())
                    <a class="boton boton-secundario boton-compacto" href="{{ $ventas->previousPageUrl() }}">Anterior</a>
                @endif

                <span class="paginacion-pagina">
                    Página {{ $ventas->currentPage() }} de {{ $ventas->lastPage() }}
                </span>

                @if($ventas->hasMorePages())
                    <a class="boton boton-secundario boton-compacto" href="{{ $ventas->nextPageUrl() }}">Siguiente</a>
                @endif
            </div>
        </div>
    @endif
</section>
@if(!$esOperador)
<section class="tarjeta"><div class="tarjeta-cabecera"><div><span class="tarjeta-sobretitulo">Cartera</span><h2>Saldos por cliente</h2></div></div><div class="lista-saldos">@foreach($clientes as $cl)@php $s=$saldoClientes[$cl->id]??['ventas'=>0,'pagos'=>0,'saldo'=>0]; @endphp<div class="saldo-cliente"><div><strong>{{ $cl->nombre }}</strong><small>Ventas S/ {{ number_format($s['ventas'],2) }} · Pagos S/ {{ number_format($s['pagos'],2) }}</small></div><div class="numero"><strong class="{{ $s['saldo']>0?'texto-peligro':'' }}">S/ {{ number_format($s['saldo'],2) }}</strong>@if(in_array(auth()->user()->rol,['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD'],true))<button type="button" class="enlace-accion" data-abrir-pago="{{ $cl->id }}">Registrar pago</button>@endif</div></div>@endforeach</div></section>
@endif
</div>
@if(!$esOperador)
@foreach($clientes as $cl)<dialog class="modal-simple" id="pago-{{ $cl->id }}"><form method="POST" action="{{ route('creditos.pago',$cl->id) }}">@csrf<div class="modal-cabecera"><div><span class="tarjeta-sobretitulo">Pago de crédito</span><h2>{{ $cl->nombre }}</h2></div><button type="button" class="boton-icono" onclick="this.closest('dialog').close()">×</button></div><div class="rejilla-campos"><div class="campo-formulario"><label>Fecha *</label><input type="date" name="fecha_pago" value="{{ now()->toDateString() }}" required></div><div class="campo-formulario"><label>Monto *</label><input type="number" name="monto" step="0.01" min="0.01" required></div><div class="campo-formulario campo-span-2"><label>Referencia</label><input name="referencia"></div><div class="campo-formulario campo-span-2"><label>Observaciones</label><textarea name="observaciones"></textarea></div></div><div class="acciones-formulario"><button type="button" class="boton boton-secundario" onclick="this.closest('dialog').close()">Cancelar</button><button class="boton boton-primario">Guardar pago</button></div></form></dialog>@endforeach
@endif
@endsection
@if(!$esOperador)
@push('scripts')<script>document.addEventListener('click',e=>{const b=e.target.closest('[data-abrir-pago]');if(b){document.getElementById('pago-'+b.dataset.abrirPago)?.showModal();}});</script>@endpush
@endif
