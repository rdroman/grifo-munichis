<a href="{{ route('seguridad-munichis.cuenta') }}" class="perfil-accion">
    <x-icono nombre="usuario" tamano="18" />
    <span>Seguridad de mi cuenta</span>
</a>
@if (auth()->user()->rol === 'ADMINISTRADOR')
    <a href="{{ route('seguridad-munichis.inicios') }}" class="perfil-accion">
        <x-icono nombre="calendario" tamano="18" />
        <span>Inicios de sesión</span>
    </a>
@endif
