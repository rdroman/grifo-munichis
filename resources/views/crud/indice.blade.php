@extends('layouts.aplicacion')

@section('titulo', $config['titulo'])
@section('encabezado', $config['titulo'])

@section('contenido')

@php
    $rutaIndice = !empty($esCatalogo) ? 'catalogos.indice' : 'crud.indice';
    $rutaCrear = !empty($esCatalogo) ? 'catalogos.crear' : 'crud.crear';
    $rutaEditar = !empty($esCatalogo) ? 'catalogos.editar' : 'crud.editar';
    $puedeEditar = $puedeEditar ?? true;
@endphp

<div class="encabezado-pagina encabezado-pagina-acciones">
    <div>
        <span class="tarjeta-sobretitulo">Gestión</span>
        <h1>{{ $config['titulo'] }}</h1>
        <p class="texto-secundario">{{ $config['descripcion'] ?? '' }}</p>
    </div>

    <a href="{{ route($rutaCrear, $modulo) }}" class="boton boton-primario">
        <x-icono nombre="agregar" tamano="18"/>
        Nuevo
    </a>
</div>

@if(auth()->user()->rol === 'OPERADOR' && $modulo === 'gastos')
    <div class="alerta" style="margin-bottom:16px;">
        <x-icono nombre="alerta"/>
        <span>
            Solo se muestran gastos asociados a tus turnos.
            Puedes registrar nuevos gastos, pero no modificar los ya guardados.
        </span>
    </div>
@endif

<section class="tarjeta filtros-tarjeta">
    <form method="GET" class="filtros-formulario filtros-extendidos">

        <div class="campo-formulario campo-busqueda">
            <label for="q">Buscar</label>

            <div class="control-con-icono">
                <x-icono nombre="buscar" tamano="18"/>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $q }}"
                    placeholder="Buscar en registros"
                >
            </div>
        </div>

        @if($campoFecha)
            <div class="campo-formulario">
                <label for="desde">Desde</label>
                <input
                    id="desde"
                    name="desde"
                    type="date"
                    value="{{ $fechaDesde }}"
                >
            </div>

            <div class="campo-formulario">
                <label for="hasta">Hasta</label>
                <input
                    id="hasta"
                    name="hasta"
                    type="date"
                    value="{{ $fechaHasta }}"
                >
            </div>
        @endif

        <div class="filtros-acciones">
            <button class="boton boton-primario boton-compacto">
                <x-icono nombre="buscar" tamano="17"/>
                Filtrar
            </button>

            <a
                href="{{ route($rutaIndice, $modulo) }}"
                class="boton boton-secundario boton-compacto"
            >
                Limpiar
            </a>
        </div>

    </form>
</section>

<section class="tarjeta tabla-tarjeta">

    <div class="tarjeta-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Listado</span>

            <h2>
                {{ number_format($total) }}
                registro{{ $total === 1 ? '' : 's' }}
            </h2>
        </div>
    </div>

    <div class="tabla-responsive">

        <table class="tabla-datos tabla-acciones">

            <thead>
                <tr>

                    @foreach($campos as $campo)
                        @if($campo['lista'] ?? false)
                            <th>{{ $campo['etiqueta'] }}</th>
                        @endif
                    @endforeach

                    @if($puedeEditar)
                        <th class="numero">Acciones</th>
                    @endif

                </tr>
            </thead>

            <tbody>

                @forelse($registros as $registro)

                    <tr>

                        @foreach($campos as $campo)

                            @if($campo['lista'] ?? false)

                                @php
                                    $v = $registro->{$campo['columna']} ?? null;
                                @endphp

                                <td data-etiqueta="{{ $campo['etiqueta'] }}">

                                    @if($campo['tipo'] === 'bool')

                                        <span class="insignia {{ $v ? 'estado-activo' : 'estado-inactivo' }}">
                                            {{ $v ? 'Sí' : 'No' }}
                                        </span>

                                    @elseif(in_array($campo['tipo'], ['fk', 'turno'], true))

                                        {{ $opciones[$campo['columna']][$v] ?? ($v ? '#'.$v : '') }}

                                    @elseif($campo['tipo'] === 'number' && $v !== null)

                                        {{
                                            number_format(
                                                (float) $v,
                                                str_contains(
                                                    (string) ($campo['step'] ?? ''),
                                                    '001'
                                                ) ? 3 : 2,
                                                '.',
                                                ','
                                            )
                                        }}

                                    @elseif($campo['tipo'] === 'enum' && $v)

                                        {{ \App\Support\Esquema::etiquetaEnum($v) }}

                                    @elseif($campo['tipo'] === 'date' && $v)

                                        {{ \Carbon\Carbon::parse($v)->format('d/m/Y') }}

                                    @else

                                        {{ $v !== null && $v !== '' ? $v : '' }}

                                    @endif

                                </td>

                            @endif

                        @endforeach

                        @if($puedeEditar)

                            <td class="numero" data-etiqueta="Acciones">

                                <a
                                    class="boton-accion"
                                    href="{{ route($rutaEditar, [$modulo, $registro->id]) }}"
                                    title="Editar"
                                >
                                    <x-icono nombre="editar" tamano="17"/>
                                </a>

                                @if ($modulo === 'gastos' && auth()->user()->rol === 'ADMINISTRADOR')
                                    @include('gastos.accion-eliminar')
                                @endif

                            </td>

                        @endif

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="{{ collect($campos)->where('lista', true)->count() + ($puedeEditar ? 1 : 0) }}"
                            class="tabla-vacia"
                        >
                            No hay registros con los filtros seleccionados.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    @if($registros->hasPages())

        <div class="paginacion-simple">

            <div>
                Mostrando
                {{ $registros->firstItem() }}{{ $registros->lastItem() }}
                de {{ $registros->total() }}
            </div>

            <div class="paginacion-acciones">

                @if(!$registros->onFirstPage())

                    <a
                        class="boton boton-secundario boton-compacto"
                        href="{{ $registros->previousPageUrl() }}"
                    >
                        Anterior
                    </a>

                @endif

                <span class="paginacion-pagina">
                    Página {{ $registros->currentPage() }}
                    de {{ $registros->lastPage() }}
                </span>

                @if($registros->hasMorePages())

                    <a
                        class="boton boton-secundario boton-compacto"
                        href="{{ $registros->nextPageUrl() }}"
                    >
                        Siguiente
                    </a>

                @endif

            </div>

        </div>

    @endif

</section>

@endsection
