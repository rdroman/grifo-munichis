<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#08752b">

    <title>Iniciar sesión | Grifo Munichis</title>

    <link
        rel="icon"
        type="image/png"
        href="{{ asset('images/isotipo-grifo-munichis.png') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/grifo-munichis.css') }}"
    >

    <style>
        .login-simple-pagina {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(8, 117, 43, .06),
                    transparent 42%
                ),
                linear-gradient(
                    135deg,
                    #f2faf4 0%,
                    #f8faf6 55%,
                    #fbfce9 100%
                );
        }

        .login-simple-tarjeta {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border: 1px solid #dce4df;
            border-radius: 24px;
            padding: 34px 30px 28px;
            box-sizing: border-box;
            box-shadow:
                0 18px 50px rgba(15, 23, 42, .08);
        }

        .login-simple-logo {
            display: block;
            width: 180px;
            max-width: 70%;
            height: auto;
            margin: 0 auto 24px;
            object-fit: contain;
        }

        .login-simple-encabezado {
            text-align: center;
            margin-bottom: 26px;
        }

        .login-simple-encabezado h1 {
            margin: 0;
            font-size: 27px;
            line-height: 1.15;
            font-weight: 800;
            color: #101828;
            letter-spacing: -.02em;
        }

        .login-simple-encabezado p {
            margin: 8px 0 0;
            font-size: 14px;
            color: #667085;
        }

        .login-simple-formulario {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .login-simple-campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .login-simple-campo label {
            font-size: 13px;
            font-weight: 700;
            color: #1d2939;
        }

        .login-simple-campo input {
            width: 100%;
            min-height: 42px;
            padding: 9px 12px;
            box-sizing: border-box;

            border: 1px solid #cfd8d3;
            border-radius: 9px;
            outline: none;

            font: inherit;
            font-size: 14px;
            color: #101828;
            background: #ffffff;

            transition:
                border-color .15s ease,
                box-shadow .15s ease;
        }

        .login-simple-campo input:focus {
            border-color: #08752b;
            box-shadow: 0 0 0 3px rgba(8, 117, 43, .12);
        }

        .login-simple-boton {
            width: 100%;
            min-height: 42px;
            border: 0;
            border-radius: 9px;
            margin-top: 0;

            background: #07852f;
            color: #ffffff;

            font: inherit;
            font-size: 14px;
            font-weight: 800;

            cursor: pointer;
            transition:
                background .15s ease,
                transform .1s ease,
                box-shadow .15s ease;

            box-shadow: 0 8px 20px rgba(8, 117, 43, .15);
        }

        .login-simple-boton:hover {
            background: #076f29;
        }

        .login-simple-boton:active {
            transform: translateY(1px);
        }

        .login-simple-error {
            margin-bottom: 18px;
            padding: 11px 13px;

            border: 1px solid #fecaca;
            border-radius: 10px;

            background: #fff1f2;
            color: #b42318;

            font-size: 13px;
            line-height: 1.4;
        }

        .login-simple-pie {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #667085;
        }

        @media (max-width: 520px) {
            .login-simple-pagina {
                padding: 14px;
            }

            .login-simple-tarjeta {
                padding: 28px 22px 24px;
                border-radius: 20px;
            }

            .login-simple-logo {
                width: 165px;
                margin-bottom: 22px;
            }

            .login-simple-encabezado h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body class="login-simple-pagina">

    <main class="login-simple-tarjeta">

        <img
            src="{{ asset('images/logo-grifo-munichis.png') }}"
            alt="Grifo Munichis"
            class="login-simple-logo"
        >

        <header class="login-simple-encabezado">
            <h1>Sistema de Gestión</h1>

            <p>
                Control Operativo, financiero y comercial
            </p>
        </header>

        @if ($errors->any())
            <div class="login-simple-error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('login.procesar') }}"
            class="login-simple-formulario"
        >
            @csrf

            <div class="login-simple-campo">
                <label for="correo_electronico">
                    Correo
                </label>

                <input
                    id="correo_electronico"
                    name="correo_electronico"
                    type="email"
                    value="{{ old('correo_electronico') }}"
                    autocomplete="username"
                    inputmode="email"
                    autofocus
                    required
                >
            </div>

            <div class="login-simple-campo">
                <label for="contrasena">
                    Contraseña
                </label>

                <input
                    id="contrasena"
                    name="contrasena"
                    type="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button
                type="submit"
                class="login-simple-boton"
            >
                Ingresar
            </button>
        </form>

        <footer class="login-simple-pie">
            Grifo Munichis S.A.C. · Yurimaguas
        </footer>

    </main>

</body>
</html>