@extends('layouts.aplicacion')
@section('titulo', 'Inicios de sesión')
@section('encabezado', 'Auditoría')
@section('contenido')
<div class="encabezado-pagina">
    <a class="enlace-volver" href="{{ route('auditoria') }}">Auditoría general</a>
    <h1>Inicios de sesión</h1>
</div>

@if ($errors->any())
    <div class="alerta alerta-error" role="alert">{{ $errors->first() }}</div>
@endif

<section class="tarjeta">
    <form method="GET" action="{{ route('seguridad-munichis.inicios') }}" class="formulario-modulo">
        <div class="rejilla-campos">
            <div class="campo-formulario">
                <label for="filtro-desde">Desde</label>
                <input id="filtro-desde" type="date" name="desde" value="{{ $filtros['desde'] }}">
            </div>
            <div class="campo-formulario">
                <label for="filtro-hasta">Hasta</label>
                <input id="filtro-hasta" type="date" name="hasta" value="{{ $filtros['hasta'] }}">
            </div>
            <div class="campo-formulario">
                <label for="filtro-usuario">Usuario</label>
                <select id="filtro-usuario" name="usuario">
                    <option value="">Todos</option>
                    @foreach ($usuarios as $usuario)
                        <option value="{{ $usuario->id }}" @selected((int) ($filtros['usuario'] ?? 0) === $usuario->id)>{{ $usuario->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="campo-formulario">
                <label for="filtro-rol">Rol al ingresar</label>
                <select id="filtro-rol" name="rol">
                    <option value="">Todos</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol }}" @selected(($filtros['rol'] ?? null) === $rol)>{{ \App\Support\Esquema::etiquetaEnum($rol) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="campo-formulario">
                <label for="filtro-ip">Dirección IP</label>
                <input id="filtro-ip" name="ip" value="{{ $filtros['ip'] ?? '' }}" maxlength="45">
            </div>
            <div class="campo-formulario">
                <label for="filtro-metodo">Método de acceso</label>
                <select id="filtro-metodo" name="metodo">
                    <option value="">Todos</option>
                    @foreach ($metodos as $codigo => $metodo)
                        <option value="{{ $codigo }}" @selected(($filtros['metodo'] ?? null) === $codigo)>{{ $metodo }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="acciones-formulario">
            <a class="boton boton-secundario" href="{{ route('seguridad-munichis.inicios') }}">Limpiar filtros</a>
            <button class="boton boton-primario" type="submit">Filtrar</button>
        </div>
    </form>
</section>

<section class="tarjeta tabla-tarjeta" style="margin-top: 1rem">
    <div class="tabla-responsive">
        <table class="tabla-datos">
            <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Correo</th><th>Rol</th><th>IP</th><th>Método</th></tr></thead>
            <tbody>
            @forelse ($registros as $registro)
                @php($datos = json_decode($registro->despues_json ?? '{}', true) ?? [])
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($registro->creado_en)->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $datos['nombre'] ?? $registro->nombre ?? '—' }}</td>
                    <td>{{ $datos['correo_electronico'] ?? $registro->correo_electronico ?? '—' }}</td>
                    <td>{{ \App\Support\Esquema::etiquetaEnum($datos['rol'] ?? $registro->rol ?? '—') }}</td>
                    <td>{{ $registro->direccion_ip ?? '—' }}</td>
                    <td>{{ $metodos[$datos['metodo'] ?? 'HISTORICO'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No hay inicios de sesión con los filtros seleccionados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($registros->hasPages())
        {{ $registros->links() }}
    @endif
</section>
@endsection
