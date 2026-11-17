@extends('layouts.aplicacion')

@section('titulo', 'Trabajadores')
@section('encabezado', 'Trabajadores')

@section('contenido')
    <div class="encabezado-pagina encabezado-pagina-acciones">
        <div>
            <span class="tarjeta-sobretitulo">Gestión de personal</span>
            <h1>Trabajadores</h1>
            <p class="texto-secundario">Administra el personal activo e histórico del Grifo Munichis.</p>
        </div>

        <a href="{{ route('trabajadores.crear') }}" class="boton boton-primario">
            <x-icono nombre="agregar" tamano="18" />
            <span>Nuevo trabajador</span>
        </a>
    </div>

    <div class="rejilla-metricas rejilla-metricas-compacta">
        <article class="tarjeta-metrica tarjeta-metrica-compacta">
            <div class="metrica-icono verde"><x-icono nombre="trabajadores" /></div>
            <div>
                <span class="metrica-etiqueta">Total registrados</span>
                <strong class="metrica-valor">{{ number_format($metricas['total']) }}</strong>
                <small class="metrica-ayuda">Histórico completo</small>
            </div>
        </article>

        <article class="tarjeta-metrica tarjeta-metrica-compacta">
            <div class="metrica-icono verde"><x-icono nombre="check" /></div>
            <div>
                <span class="metrica-etiqueta">Activos</span>
                <strong class="metrica-valor">{{ number_format($metricas['activos']) }}</strong>
                <small class="metrica-ayuda">Personal vigente</small>
            </div>
        </article>

        <article class="tarjeta-metrica tarjeta-metrica-compacta">
            <div class="metrica-icono rojo"><x-icono nombre="desactivar" /></div>
            <div>
                <span class="metrica-etiqueta">Inactivos</span>
                <strong class="metrica-valor">{{ number_format($metricas['inactivos']) }}</strong>
                <small class="metrica-ayuda">Personal histórico</small>
            </div>
        </article>

        <article class="tarjeta-metrica tarjeta-metrica-compacta">
            <div class="metrica-icono azul"><x-icono nombre="usuario" /></div>
            <div>
                <span class="metrica-etiqueta">Con cuenta</span>
                <strong class="metrica-valor">{{ number_format($metricas['con_cuenta']) }}</strong>
                <small class="metrica-ayuda">Vinculados a un usuario</small>
            </div>
        </article>
    </div>

    <section class="tarjeta filtros-tarjeta">
        <form method="GET" action="{{ route('trabajadores.indice') }}" class="filtros-formulario">
            <div class="campo-formulario campo-busqueda">
                <label for="q">Buscar</label>
                <div class="control-con-icono">
                    <x-icono nombre="buscar" tamano="18" />
                    <input
                        id="q"
                        name="q"
                        type="search"
                        value="{{ $texto }}"
                        placeholder="Nombre o documento"
                    >
                </div>
            </div>

            <div class="campo-formulario filtro-estado">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <option value="activos" @selected($estado === 'activos')>Activos</option>
                    <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
                    <option value="todos" @selected($estado === 'todos')>Todos</option>
                </select>
            </div>

            <div class="filtros-acciones">
                <button type="submit" class="boton boton-primario boton-compacto">
                    <x-icono nombre="buscar" tamano="17" />
                    Filtrar
                </button>

                @if ($texto !== '' || $estado !== 'activos')
                    <a href="{{ route('trabajadores.indice') }}" class="boton boton-secundario boton-compacto">
                        Limpiar
                    </a>
                @endif
            </div>
        </form>
    </section>

    <section class="tarjeta tabla-tarjeta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Listado</span>
                <h2>Personal registrado</h2>
            </div>
            <span class="texto-contador">{{ $trabajadores->total() }} resultado{{ $trabajadores->total() === 1 ? '' : 's' }}</span>
        </div>

        <div class="tabla-responsive">
            <table class="tabla-datos tabla-acciones">
                <thead>
                    <tr>
                        <th>Trabajador</th>
                        <th>Documento</th>
                        <th>Ingreso</th>
                        <th>Estado</th>
                        <th>Cuenta</th>
                        <th class="numero">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trabajadores as $trabajador)
                        <tr>
                            <td data-etiqueta="Trabajador">
                                <div class="celda-persona">
                                    <div class="avatar-lista">{{ mb_strtoupper(mb_substr($trabajador->nombre_completo, 0, 1)) }}</div>
                                    <div>
                                        <strong>{{ $trabajador->nombre_completo }}</strong>
                                        <small>ID #{{ $trabajador->id }}</small>
                                    </div>
                                </div>
                            </td>
                            <td data-etiqueta="Documento">
                                {{ $trabajador->numero_documento ?: '—' }}
                            </td>
                            <td data-etiqueta="Ingreso">
                                {{ $trabajador->fecha_ingreso?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td data-etiqueta="Estado">
                                <span class="insignia {{ $trabajador->activo ? 'estado-activo' : 'estado-inactivo' }}">
                                    {{ $trabajador->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td data-etiqueta="Cuenta">
                                @if ($trabajador->usuario_id)
                                    <div class="cuenta-vinculada">
                                        <strong>{{ $trabajador->usuario_correo ?: 'Usuario #' . $trabajador->usuario_id }}</strong>
                                        <small>{{ ucfirst(mb_strtolower($trabajador->usuario_rol ?? '')) }}</small>
                                    </div>
                                @else
                                    <span class="texto-muted">Sin cuenta</span>
                                @endif
                            </td>
                            <td data-etiqueta="Acciones" class="numero">
                                <div class="acciones-tabla">
                                    <a
                                        href="{{ route('trabajadores.editar', $trabajador->id) }}"
                                        class="boton-accion"
                                        title="Editar trabajador"
                                        aria-label="Editar {{ $trabajador->nombre_completo }}"
                                    >
                                        <x-icono nombre="editar" tamano="17" />
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('trabajadores.estado', $trabajador->id) }}"
                                        onsubmit="return confirm('{{ $trabajador->activo ? '¿Desactivar a este trabajador?' : '¿Activar a este trabajador?' }}')"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <button
                                            type="submit"
                                            class="boton-accion {{ $trabajador->activo ? 'peligro' : 'exito' }}"
                                            title="{{ $trabajador->activo ? 'Desactivar' : 'Activar' }}"
                                            aria-label="{{ $trabajador->activo ? 'Desactivar' : 'Activar' }} {{ $trabajador->nombre_completo }}"
                                        >
                                            <x-icono nombre="{{ $trabajador->activo ? 'desactivar' : 'activar' }}" tamano="17" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="tabla-vacia">
                                No se encontraron trabajadores con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($trabajadores->hasPages())
            <div class="paginacion-simple">
                <div>
                    Mostrando {{ $trabajadores->firstItem() }}–{{ $trabajadores->lastItem() }} de {{ $trabajadores->total() }}
                </div>
                <div class="paginacion-acciones">
                    @if ($trabajadores->onFirstPage())
                        <span class="boton boton-secundario boton-compacto deshabilitado">Anterior</span>
                    @else
                        <a href="{{ $trabajadores->previousPageUrl() }}" class="boton boton-secundario boton-compacto">Anterior</a>
                    @endif

                    <span class="paginacion-pagina">Página {{ $trabajadores->currentPage() }} de {{ $trabajadores->lastPage() }}</span>

                    @if ($trabajadores->hasMorePages())
                        <a href="{{ $trabajadores->nextPageUrl() }}" class="boton boton-secundario boton-compacto">Siguiente</a>
                    @else
                        <span class="boton boton-secundario boton-compacto deshabilitado">Siguiente</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endsection
