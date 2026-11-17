@extends('layouts.aplicacion')

@section('titulo', $esEdicion ? 'Editar trabajador' : 'Nuevo trabajador')
@section('encabezado', $esEdicion ? 'Editar trabajador' : 'Nuevo trabajador')

@section('contenido')
    <div class="encabezado-pagina encabezado-pagina-acciones">
        <div>
            <a href="{{ route('trabajadores.indice') }}" class="enlace-volver">
                <x-icono nombre="volver" tamano="17" />
                Trabajadores
            </a>
            <h1>{{ $esEdicion ? 'Editar trabajador' : 'Nuevo trabajador' }}</h1>
            <p class="texto-secundario">
                {{ $esEdicion
                    ? 'Actualiza la información laboral y el estado del trabajador.'
                    : 'Registra un nuevo trabajador en el establecimiento.' }}
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alerta alerta-error" role="alert">
            <x-icono nombre="alerta" />
            <div>
                <strong>No se pudo guardar la información.</strong>
                <span>Revisa los campos marcados e inténtalo nuevamente.</span>
            </div>
        </div>
    @endif

    <div class="rejilla-formulario-modulo">
        <section class="tarjeta">
            <div class="tarjeta-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Datos generales</span>
                    <h2>Información del trabajador</h2>
                </div>
            </div>

            <form
                method="POST"
                action="{{ $esEdicion ? route('trabajadores.actualizar', $trabajador->id) : route('trabajadores.guardar') }}"
                class="formulario-modulo"
            >
                @csrf
                @if ($esEdicion)
                    @method('PUT')
                @endif

                <div class="rejilla-campos">
                    <div class="campo-formulario campo-span-2">
                        <label for="nombre_completo">Nombre completo <span class="requerido">*</span></label>
                        <input
                            id="nombre_completo"
                            name="nombre_completo"
                            type="text"
                            maxlength="150"
                            value="{{ old('nombre_completo', $trabajador?->nombre_completo) }}"
                            autocomplete="off"
                            required
                            autofocus
                            class="{{ $errors->has('nombre_completo') ? 'invalido' : '' }}"
                        >
                        @error('nombre_completo')
                            <small class="error-campo">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="campo-formulario">
                        <label for="numero_documento">Número de documento</label>
                        <input
                            id="numero_documento"
                            name="numero_documento"
                            type="text"
                            maxlength="20"
                            value="{{ old('numero_documento', $trabajador?->numero_documento) }}"
                            class="{{ $errors->has('numero_documento') ? 'invalido' : '' }}"
                        >
                        @error('numero_documento')
                            <small class="error-campo">{{ $message }}</small>
                        @enderror
                    </div>

                    @php
                        $estadoActivo = (bool) old('activo', $trabajador?->activo ?? true);
                    @endphp
                    <div class="campo-formulario">
                        <label for="activo">Estado</label>
                        <input type="hidden" name="activo" value="0">
                        <div class="gm-estado-trabajador">
                            <label class="gm-switch" for="activo">
                                <input
                                    id="activo"
                                    name="activo"
                                    type="checkbox"
                                    value="1"
                                    data-switch-estado
                                    @checked($estadoActivo)
                                >
                                <span class="gm-switch__slider" aria-hidden="true"></span>
                                <span class="gm-switch__text" data-texto-estado data-texto-activo="Trabajador activo" data-texto-inactivo="Trabajador inactivo">
                                    {{ $estadoActivo ? 'Trabajador activo' : 'Trabajador inactivo' }}
                                </span>
                            </label>
                        </div>
                        @error('activo')
                            <small class="error-campo">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="campo-formulario">
                        <label for="fecha_ingreso">Fecha de ingreso</label>
                        <input
                            id="fecha_ingreso"
                            name="fecha_ingreso"
                            type="date"
                            value="{{ old('fecha_ingreso', $trabajador?->fecha_ingreso?->format('Y-m-d')) }}"
                            class="{{ $errors->has('fecha_ingreso') ? 'invalido' : '' }}"
                        >
                        @error('fecha_ingreso')
                            <small class="error-campo">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="campo-formulario">
                        <label for="fecha_cese">Fecha de cese</label>
                        <input
                            id="fecha_cese"
                            name="fecha_cese"
                            type="date"
                            value="{{ old('fecha_cese', $trabajador?->fecha_cese?->format('Y-m-d')) }}"
                            class="{{ $errors->has('fecha_cese') ? 'invalido' : '' }}"
                        >
                        @error('fecha_cese')
                            <small class="error-campo">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="campo-formulario campo-span-2">
                        <label for="observaciones">Observaciones</label>
                        <textarea
                            id="observaciones"
                            name="observaciones"
                            rows="5"
                            maxlength="2000"
                            class="{{ $errors->has('observaciones') ? 'invalido' : '' }}"
                        >{{ old('observaciones', $trabajador?->observaciones) }}</textarea>
                        @error('observaciones')
                            <small class="error-campo">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="acciones-formulario">
                    <a href="{{ route('trabajadores.indice') }}" class="boton boton-secundario">Cancelar</a>
                    <button type="submit" class="boton boton-primario">
                        <x-icono nombre="guardar" tamano="18" />
                        {{ $esEdicion ? 'Guardar cambios' : 'Registrar trabajador' }}
                    </button>
                </div>
            </form>
        </section>

        <aside class="columna-lateral-formulario">
            @if ($esEdicion)
                <section class="tarjeta tarjeta-info-lateral">
                    <span class="tarjeta-sobretitulo">Registro</span>
                    <h2>{{ $trabajador->nombre_completo }}</h2>

                    <dl class="lista-definicion">
                        <div>
                            <dt>ID</dt>
                            <dd>#{{ $trabajador->id }}</dd>
                        </div>
                        <div>
                            <dt>Estado</dt>
                            <dd>
                                <span class="insignia {{ $trabajador->activo ? 'estado-activo' : 'estado-inactivo' }}">
                                    {{ $trabajador->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt>Registrado</dt>
                            <dd>{{ $trabajador->creado_en?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="tarjeta tarjeta-info-lateral">
                    <span class="tarjeta-sobretitulo">Cuenta de acceso</span>
                    <h2>Usuario vinculado</h2>

                    @if ($trabajador->usuario)
                        <div class="cuenta-detalle">
                            <div class="avatar-lista grande">
                                {{ mb_strtoupper(mb_substr($trabajador->usuario->nombre, 0, 1)) }}
                            </div>
                            <div>
                                <strong>{{ $trabajador->usuario->nombre }}</strong>
                                <span>{{ $trabajador->usuario->correo_electronico }}</span>
                                <small>{{ ucfirst(mb_strtolower($trabajador->usuario->rol)) }} · {{ $trabajador->usuario->activo ? 'Activo' : 'Inactivo' }}</small>
                            </div>
                        </div>
                    @else
                        <div class="estado-vacio compacto">
                            <x-icono nombre="usuario" tamano="30" />
                            <p>Este trabajador no tiene una cuenta de usuario vinculada.</p>
                        </div>
                    @endif
                </section>
            @else
                <section class="tarjeta tarjeta-info-lateral">
                    <span class="tarjeta-sobretitulo">Recomendación</span>
                    <h2>Registro de personal</h2>
                    <p class="texto-secundario texto-lateral">
                        El trabajador quedará disponible para asignaciones operativas. La cuenta de acceso al sistema se administra por separado.
                    </p>
                </section>
            @endif
        </aside>
    </div>
@endsection
