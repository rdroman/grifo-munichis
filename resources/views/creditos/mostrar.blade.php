@extends('layouts.aplicacion')
@section('titulo','Detalle de crédito') @section('encabezado','Créditos')
@section('contenido')
<div class="encabezado-pagina"><a class="enlace-volver" href="{{ route('creditos.indice') }}"><x-icono nombre="volver" tamano="17"/> Créditos</a><h1>{{ $venta->cliente }}</h1><p class="texto-secundario">Venta #{{ $venta->id }} · Total S/ {{ number_format($total,2) }}</p></div>
<section class="tarjeta"><div class="tabla-responsive"><table class="tabla-datos"><thead><tr><th>Producto</th><th class="numero">Cantidad</th><th class="numero">Precio</th><th class="numero">Importe</th></tr></thead><tbody>@foreach($items as $i)@php $cant=$c['cantidad']?($i->{$c['cantidad']}??0):0; $pre=$c['precio']?($i->{$c['precio']}??0):0; $imp=$c['importe']?($i->{$c['importe']}??($cant*$pre)):($cant*$pre); @endphp<tr><td>{{ $i->producto_codigo }} · {{ $i->producto }}</td><td class="numero">{{ number_format((float)$cant,4) }}</td><td class="numero">S/ {{ number_format((float)$pre,4) }}</td><td class="numero"><strong>S/ {{ number_format((float)$imp,2) }}</strong></td></tr>@endforeach</tbody></table></div></section>
@endsection
