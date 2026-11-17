@extends('layouts.aplicacion')
@section('titulo','Descuentos')
@section('encabezado','Descuentos')
@section('contenido')

<div class="encabezado-pagina">
    <div>
        <span class="tarjeta-sobretitulo">Ventas de combustible</span>
        <h1>Descuentos</h1>
        <p class="texto-secundario">El precio se obtiene del precio de venta vigente del producto. Solo ADMINISTRADOR puede modificarlo.</p>
    </div>
    @if(in_array(auth()->user()->rol,['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR'],true))
        <a class="boton boton-primario" href="{{ route('descuentos.crear') }}"><x-icono nombre="agregar" tamano="17"/> Registrar descuento</a>
    @endif
</div>

@if(session('exito'))<div class="alerta alerta-exito">{{ session('exito') }}</div>@endif
@if($errors->any())<div class="alerta alerta-error">{{ $errors->first() }}</div>@endif

<section class="tarjeta tabla-tarjeta">
    <div class="tabla-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>Fecha</th><th>Turno</th><th>Operador</th><th>Producto</th>
                    <th class="numero">Galones</th><th class="numero">Precio</th><th class="numero">Importe</th><th>Motivo</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($descuentos as $d)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($d->fecha_descuento)->format('d/m/Y') }}</td>
                    <td>{{ $d->turno_codigo ?: '—' }}</td>
                    <td>{{ $d->trabajador ?: '—' }}</td>
                    <td><strong>{{ $d->producto_codigo }}</strong><small class="bloque">{{ $d->producto }}</small></td>
                    <td class="numero">{{ number_format((float)$d->galones,3) }}</td>
                    <td class="numero">S/ {{ number_format((float)$d->precio_unitario_descuento,4) }}</td>
                    <td class="numero"><strong>S/ {{ number_format((float)$d->importe_efectivo,2) }}</strong></td>
                    <td>{{ $d->motivo ?: '—' }}</td>
                    <td>
                        @if(in_array(auth()->user()->rol,['ADMINISTRADOR','GERENCIA','SUPERVISOR'],true))
                            <a class="boton-accion" href="{{ route('descuentos.editar',$d->id) }}" title="Editar"><x-icono nombre="editar" tamano="17"/></a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="tabla-vacia">No hay descuentos registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $descuentos->links() }}</div>
</section>
@endsection
