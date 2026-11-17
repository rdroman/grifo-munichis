<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#08752b">
    <title>Verificación en dos pasos | Grifo Munichis</title>
    <link rel="icon" href="{{ asset('images/logo-grifo-munichis.png') }}">
    <link rel="stylesheet" href="{{ asset('css/grifo-munichis.css') }}">
    <link rel="stylesheet" href="{{ asset('css/seguridad-munichis.css') }}">
</head>
<body class="munichis-verificacion-pagina">
    <main class="tarjeta munichis-seguridad-tarjeta munichis-verificacion-tarjeta" aria-labelledby="munichis-verificacion-titulo">
        <h1 id="munichis-verificacion-titulo">Verificación en dos pasos</h1>
        <p class="munichis-seguridad-texto">Ingrese el código de seis dígitos de su aplicación autenticadora o un código de recuperación.</p>

        @error('codigo')
            <div class="alerta alerta-error" role="alert">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('seguridad-munichis.token.validar') }}" class="munichis-seguridad-formulario">
            @csrf
            <div class="campo-formulario">
                <label for="codigo">Código de verificación</label>
                <input id="codigo" name="codigo" type="text" maxlength="32" autocomplete="one-time-code"
                       spellcheck="false" autocapitalize="characters" required autofocus>
            </div>
            <button class="boton boton-primario munichis-seguridad-boton" type="submit">Verificar e iniciar sesión</button>
        </form>

        <form method="POST" action="{{ route('seguridad-munichis.token.cancelar') }}" class="munichis-seguridad-formulario">
            @csrf
            <button type="submit" class="boton munichis-seguridad-boton munichis-verificacion-volver">Volver al inicio de sesión</button>
        </form>
    </main>
</body>
</html>
