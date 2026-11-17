@extends('layouts.aplicacion')

@section('titulo', 'Eliminar gasto')
@section('encabezado', 'Gastos')

@section('contenido')
    <div class="encabezado-pagina">
        <h1>Eliminar gasto</h1>
        <p class="texto-secundario">Confirma el registro que deseas eliminar.</p>
    </div>

    <section class="tarjeta tabla-tarjeta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Gasto #{{ $gasto->id }}</span>
                <h2>{{ $gasto->detalle }}</h2>
                <p class="texto-secundario">
                    {{ \Carbon\Carbon::parse($gasto->fecha_gasto)->format('d/m/Y') }}
                    · S/ {{ number_format((float) $gasto->{$columnaMonto}, 2, '.', ',') }}
                    @if ($gasto->turno_operativo_id)
                        · Turno #{{ $gasto->turno_operativo_id }}
                    @endif
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('gastos.eliminar', ['id' => $gasto->id] + $filtros) }}"
              class="filtros-acciones">
            @csrf
            @method('DELETE')
            <a class="boton boton-secundario" href="{{ route('crud.indice', ['modulo' => 'gastos'] + $filtros) }}">
                Cancelar
            </a>
            <button type="submit" class="boton boton-primario">Eliminar gasto</button>
        </form>
    </section>
@endsection
