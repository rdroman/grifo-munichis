@extends('layouts.aplicacion')
@section('titulo','Configuración') @section('encabezado','Configuración')
@section('contenido')
<div class="encabezado-pagina"><span class="tarjeta-sobretitulo">Sistema</span><h1>Configuración</h1><p class="texto-secundario">Usuarios y catálogos maestros del Grifo Munichis.</p></div>
<div class="rejilla-configuracion"><a class="tarjeta tarjeta-enlace" href="{{ route('usuarios.indice') }}"><div class="metrica-icono azul"><x-icono nombre="usuario"/></div><div><h2>Usuarios</h2><p>{{ $usuarios }} cuentas registradas</p></div><x-icono nombre="chevron"/></a>@foreach($catalogos as $c)<a class="tarjeta tarjeta-enlace" href="{{ route('catalogos.indice',$c['clave']) }}"><div class="metrica-icono verde"><x-icono nombre="configuracion"/></div><div><h2>{{ $c['titulo'] }}</h2><p>{{ $c['total'] }} registros</p></div><x-icono nombre="chevron"/></a>@endforeach</div>
@endsection
