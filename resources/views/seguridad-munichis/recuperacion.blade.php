@extends('layouts.aplicacion')
@section('titulo', 'Códigos de recuperación')
@section('encabezado', 'Mi cuenta')
@section('contenido')
<div class="encabezado-pagina"><h1>Códigos de recuperación</h1></div>
<section class="tarjeta">
    <p>La verificación en dos pasos está activa. Guarde estos códigos en un lugar seguro. Se muestran una sola vez y cada uno se puede usar en un solo inicio de sesión.</p>
    <ul>
        @foreach ($codigos as $codigo)
            <li><code>{{ implode('-', str_split($codigo, 5)) }}</code></li>
        @endforeach
    </ul>
    <a class="boton boton-primario" href="{{ route('seguridad-munichis.cuenta') }}">Volver a mi cuenta</a>
</section>
@endsection
