@extends('layouts.aplicacion')

@section('titulo', $titulo)
@section('encabezado', $titulo)

@section('contenido')
    <section class="encabezado-pagina">
        <div>
            <p class="eyebrow">Módulo</p>
            <h1>{{ $titulo }}</h1>
            <p class="texto-secundario">{{ $descripcion }}</p>
        </div>
    </section>

    <section class="tarjeta pagina-proximamente">
        <div class="proximamente-icono">
            <x-icono :nombre="$icono" tamano="42" />
        </div>

        <div>
            <span class="insignia desarrollo">En desarrollo</span>
            <h2>La navegación ya está preparada</h2>
            <p>
                Este módulo forma parte de la estructura de Grifo Munichis v2.0.
                Su ruta, menú, permisos visuales y diseño responsive ya están integrados.
            </p>
            <p>
                La siguiente etapa será implementar aquí las operaciones reales contra la base de datos,
                manteniendo la nomenclatura en español y la auditoría del sistema.
            </p>

            <a href="{{ route('panel') }}" class="boton boton-primario">
                <x-icono nombre="panel" tamano="18" />
                Volver al panel
            </a>
        </div>
    </section>
@endsection
