@php use App\Support\PermisosSupervisor; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#08752b">

    <title>@yield('titulo', 'Panel') | Grifo Munichis</title>

    <link rel="icon" type="image/png" href="{{ asset('images/isotipo-grifo-munichis.png') }}">
    <link rel="stylesheet" href="{{ asset('css/grifo-munichis.css') }}">

    @stack('estilos')
    @include('partials.correcciones-ui')
    {{-- PARCHE_UI_DIRECTO_20260925_V2 --}}
    <link rel="stylesheet" href="{{ asset('css/parche_ui_directo_20260925.css') }}">
</head>
<body class="aplicacion">
    <div class="capa-menu" data-capa-menu></div>

    <aside class="barra-lateral" data-barra-lateral aria-label="Menú lateral">
        <div class="marca-lateral">
            <a href="{{ route('panel') }}" aria-label="Ir al panel">
                <img
                    src="{{ asset('images/logo-grifo-munichis.png') }}"
                    alt="Grifo Munichis"
                    class="logo-lateral"
                >
            </a>
        </div>

        <div class="menu-contenedor">
            @include('partials.navegacion')
        </div>

        <div class="lateral-pie">
            <div class="lateral-usuario">
                <div class="avatar-usuario">
                    {{ mb_strtoupper(mb_substr(auth()->user()->nombre, 0, 1)) }}
                </div>
                <div class="lateral-usuario-datos">
                    <strong>{{ auth()->user()->nombre }}</strong>
                    <span>{{ ucfirst(mb_strtolower(auth()->user()->rol)) }}</span>
                </div>
            </div>
        </div>
    </aside>

    <div class="envoltura-principal">
        <header class="barra-superior">
            <div class="superior-izquierda">
                <button
                    type="button"
                    class="boton-icono boton-menu"
                    data-boton-menu
                    aria-label="Abrir menú"
                    aria-expanded="false"
                >
                    <x-icono nombre="menu" tamano="24" />
                </button>

                <div class="titulo-superior">
                    <span class="superior-contexto">Grifo Munichis</span>
                    <strong>@yield('encabezado', 'Panel')</strong>
                </div>
            </div>

            <div class="superior-derecha">
                <div class="fecha-superior">
                    <x-icono nombre="calendario" tamano="18" />
                    <span>{{ now()->translatedFormat('d M Y') }}</span>
                </div>

                <details class="perfil-menu">
                    <summary aria-label="Opciones de usuario">
                        <div class="avatar-usuario avatar-superior">
                            {{ mb_strtoupper(mb_substr(auth()->user()->nombre, 0, 1)) }}
                        </div>
                        <div class="perfil-resumen">
                            <strong>{{ auth()->user()->nombre }}</strong>
                            <span>{{ ucfirst(mb_strtolower(auth()->user()->rol)) }}</span>
                        </div>
                    </summary>

                    <div class="perfil-desplegable">
                        <div class="perfil-cabecera">
                            <strong>{{ auth()->user()->nombre }}</strong>
                            <span>{{ auth()->user()->correo_electronico }}</span>
                        </div>

                        @include('seguridad-munichis.menu')

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="perfil-accion peligro">
                                <x-icono nombre="cerrar" tamano="18" />
                                <span>Cerrar sesión</span>
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="contenido-principal">
            @if (session('exito'))
                <div class="alerta alerta-exito" role="status">
                    <x-icono nombre="check" />
                    <span>{{ session('exito') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="alerta alerta-error" role="alert">
                    <x-icono nombre="alerta" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('contenido')
        </main>

        <footer class="pie-aplicacion">
            <span>Grifo Munichis S.A.C.</span>
            <span>•</span>
            <span>Sistema de Gestión v2.0</span>
        </footer>
    </div>

    <script src="{{ asset('js/grifo-munichis.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
