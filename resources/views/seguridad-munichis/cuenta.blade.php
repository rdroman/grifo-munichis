@extends('layouts.aplicacion')
@section('titulo', 'Seguridad de mi cuenta')
@section('encabezado', 'Mi cuenta')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/seguridad-munichis.css') }}">
@endpush
@push('scripts')
    @if ($qr)
        <script src="{{ asset('js/qrcode-generator-2.0.4.js') }}" defer></script>
    @endif
    <script src="{{ asset('js/seguridad-munichis.js') }}" defer></script>
@endpush

@section('contenido')
<div class="munichis-cuenta-seguridad">
    <header class="munichis-seguridad-cabecera">
        <h1>Seguridad de mi cuenta</h1>
    </header>

    @if ($errors->any())
        <div class="alerta alerta-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="tarjeta munichis-seguridad-tarjeta" aria-labelledby="munichis-cambiar-titulo">
        <h2 id="munichis-cambiar-titulo">Cambiar contraseña</h2>
        <form class="munichis-seguridad-formulario" method="POST" action="{{ route('seguridad-munichis.clave') }}">
            @csrf
            @include('seguridad-munichis.campo-clave', ['id' => 'clave-actual', 'nombre' => 'current_password', 'etiqueta' => 'Contraseña actual'])
            @include('seguridad-munichis.campo-clave', ['id' => 'clave-nueva', 'nombre' => 'password', 'etiqueta' => 'Nueva contraseña'])
            @include('seguridad-munichis.campo-clave', ['id' => 'clave-confirmacion', 'nombre' => 'password_confirmation', 'etiqueta' => 'Confirmar nueva contraseña'])

            <button type="submit" class="boton boton-primario munichis-seguridad-boton">Cambiar contraseña</button>
        </form>
    </section>

    <section class="tarjeta munichis-seguridad-tarjeta" aria-labelledby="munichis-dos-pasos-titulo">
        <h2 id="munichis-dos-pasos-titulo">Verificación en dos pasos</h2>
        @if ($activado)
            <p class="munichis-seguridad-texto">Activa. El sistema pide un código de la aplicación autenticadora cada vez que inicie sesión.</p>
            <details class="munichis-seguridad-detalle" @if ($errors->hasAny(['codigo', 'current_password'])) open @endif>
                <summary>Desactivar verificación en dos pasos</summary>
                <form class="munichis-seguridad-formulario" method="POST" action="{{ route('seguridad-munichis.token.desactivar') }}">
                    @csrf
                    @include('seguridad-munichis.campo-clave', ['id' => 'desactivar-clave', 'nombre' => 'current_password', 'etiqueta' => 'Contraseña actual'])
                    <div class="campo-formulario">
                        <label for="desactivar-codigo">Código de la aplicación o de recuperación</label>
                        <input id="desactivar-codigo" type="text" name="codigo" autocomplete="one-time-code" maxlength="32" required>
                    </div>
                    <button type="submit" class="boton boton-secundario munichis-seguridad-boton">Desactivar</button>
                </form>
            </details>
        @elseif ($qr)
            <p class="munichis-seguridad-texto">En Google Authenticator, pulse <strong>+</strong> y elija <strong>Escanear un código QR</strong>.</p>
            <canvas class="munichis-seguridad-qr" data-munichis-qr="{{ $qr }}" role="img"
                    aria-label="Código QR para vincular esta cuenta en Grifo Munichis" hidden></canvas>
            <details class="munichis-seguridad-detalle">
                <summary>Usar clave manual</summary>
                <p>En la aplicación, elija <strong>Introducir clave de configuración</strong>. Use <strong>Grifo Munichis</strong> como nombre y <strong>Basada en el tiempo</strong> como tipo.</p>
                <p><code>{{ $secreto }}</code></p>
            </details>

            <form class="munichis-seguridad-formulario" method="POST" action="{{ route('seguridad-munichis.token.activar') }}">
                @csrf
                <div class="campo-formulario">
                    <label for="activar-codigo">Código de seis dígitos de la aplicación</label>
                    <input id="activar-codigo" type="text" name="codigo" autocomplete="one-time-code" inputmode="numeric"
                           pattern="[0-9]{6}" maxlength="6" required>
                </div>
                <button type="submit" class="boton boton-primario munichis-seguridad-boton">Activar verificación</button>
            </form>
        @else
            <p class="munichis-seguridad-texto">Configure su celular para confirmar cada inicio de sesión con un código de seis dígitos.</p>
            <form class="munichis-seguridad-formulario" method="POST" action="{{ route('seguridad-munichis.token.preparar') }}">
                @csrf
                @include('seguridad-munichis.campo-clave', ['id' => 'preparar-clave', 'nombre' => 'current_password', 'etiqueta' => 'Contraseña actual'])
                <button type="submit" class="boton boton-primario munichis-seguridad-boton">Configurar autenticador</button>
            </form>
        @endif
    </section>
</div>
@endsection
