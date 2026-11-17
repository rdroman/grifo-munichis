@extends('layouts.aplicacion')
@section('titulo', $modoRegistro === 'extraordinario' ? 'Registrar corte extraordinario' : 'Registrar turno')
@section('encabezado', $modoRegistro === 'extraordinario' ? 'Corte extraordinario' : 'Registrar turno')
@section('contenido')

<div class="encabezado-pagina">
    <a href="{{ route('turnos.indice') }}" class="enlace-volver">
        <x-icono nombre="volver" tamano="17"/> Turnos
    </a>

    <h1>
        {{ $modoRegistro === 'extraordinario'
            ? 'Registrar corte extraordinario'
            : 'Registrar turno operativo' }}
    </h1>
    @if($modoRegistro === 'extraordinario')
        <span class="insignia turno gm-insignia-corte">{{ $secuencia['codigo'] }} · Corte extraordinario</span>
    @endif
</div>

@if($errors->any())
    <div class="alerta alerta-error">
        <x-icono nombre="alerta"/>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

@if($reinicioPendiente)
    <div class="alerta alerta-advertencia">
        <x-icono nombre="alerta"/>
        <span>
            Existe un reinicio de totalizadores pendiente.
            Las mangueras afectadas usarán <strong>0.000</strong> como lectura inicial
            en este registro.
        </span>
    </div>
@endif

<form method="POST" action="{{ route('turnos.guardar') }}" class="formulario-modulo gm-formulario-compacto">
    @csrf

    <input type="hidden" name="modo_registro" value="{{ $modoRegistro }}">
    <input type="hidden" name="fecha_operacion" value="{{ $secuencia['fecha'] }}">
    <input type="hidden" name="tipo_turno_id" value="{{ $secuencia['tipo_turno_id'] }}">

    <section class="tarjeta gm-tarjeta-compacta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Cabecera</span>
                <h2>Datos del turno</h2>
            </div>
        </div>

        <div class="rejilla-campos">
            <div class="campo-formulario">
                <label>Fecha *</label>
                <input
                    type="text"
                    value="{{ \Carbon\Carbon::parse($secuencia['fecha'])->format('d/m/Y') }}"
                    readonly
                >
            </div>

            <div class="campo-formulario">
                <label>Tipo de turno *</label>
                <input
                    type="text"
                    value="{{ $secuencia['codigo'] }} · {{ $secuencia['nombre'] }}"
                    readonly
                >
            </div>

            <div class="campo-formulario">
                <label>Trabajador</label>

                @if($esOperador)
                    @php $trabajadorPropio = $trabajadores->first(); @endphp

                    <input
                        value="{{ $trabajadorPropio?->nombre_completo ?? 'Sin trabajador vinculado' }}"
                        disabled
                    >
                    <input
                        type="hidden"
                        name="trabajador_id"
                        value="{{ $trabajadorPropio?->id }}"
                    >
                @else
                    <select name="trabajador_id">
                        <option value="">Seleccione</option>

                        @foreach($trabajadores as $t)
                            <option
                                value="{{ $t->id }}"
                                @selected(old('trabajador_id') == $t->id)
                            >
                                {{ $t->nombre_completo }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            @if($motivos->count())
                <div class="campo-formulario">
                    <label>Motivo</label>

                    <select name="motivo_turno_id">
                        <option value="">Seleccione</option>

                        @foreach($motivos as $m)
                            <option
                                value="{{ $m->id }}"
                                @selected(old('motivo_turno_id', $motivoTurnoNormalId ?? null) == $m->id)
                            >
                                {{ $m->codigo ?? '' }} · {{ $m->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="campo-formulario campo-span-2">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="2">{{ old('observaciones') }}</textarea>
            </div>
        </div>
    </section>

    <section class="tarjeta gm-tarjeta-compacta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Surtidores</span>
                <h2>Lecturas de totalizadores</h2>
            </div>
        </div>

        <div class="tabla-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Manguera</th>
                        <th>Lectura anterior</th>
                        <th>Lectura final</th>
                        <th>Galones despachados</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($mangueras as $m)
                        @php
                            $lecturaAnterior = $m->lectura_anterior !== null
                                ? (float) $m->lectura_anterior
                                : null;

                            $lecturaFinalOld = old(
                                'lecturas.' . $m->id . '.lectura'
                            );

                            $reiniciada = (bool) ($m->reinicio_pendiente ?? false);
                        @endphp

                        <tr
                            class="fila-totalizador"
                            data-lectura-anterior="{{
                                $lecturaAnterior !== null
                                    ? number_format($lecturaAnterior, 3, '.', '')
                                    : ''
                            }}"
                        >
                            <td>
                                <strong>{{ $m->producto_codigo }}</strong>
                                @if($m->producto)
                                    <small class="bloque">{{ $m->producto }}</small>
                                @endif
                            </td>

                            <td>
                                <strong>{{ $m->codigo }}</strong>
                                @if($m->lado)
                                    <small class="bloque">{{ $m->lado }}</small>
                                @endif
                            </td>

                            <td>
                                @if($reiniciada)
                                    <strong>0.000</strong>
                                    <small class="bloque texto-secundario">
                                        Base por reinicio
                                    </small>
                                @elseif($lecturaAnterior !== null)
                                    <strong>
                                        {{ number_format($lecturaAnterior, 3, '.', ',') }}
                                    </strong>
                                @else
                                    <span class="texto-secundario">
                                        Sin lectura anterior
                                    </span>
                                @endif
                            </td>

                            <td>
                                <input
                                    class="input-tabla lectura-final"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    name="lecturas[{{ $m->id }}][lectura]"
                                    value="{{ $lecturaFinalOld }}"
                                    required
                                >
                            </td>

                            <td>
                                <input
                                    class="input-tabla galones-calculados"
                                    type="number"
                                    step="0.001"
                                    readonly
                                    tabindex="-1"
                                >
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta gm-tarjeta-compacta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Resumen</span>
                <h2>Información adicional del turno</h2>
            </div>
        </div>

        <div class="rejilla-campos">
            <div class="campo-formulario">
                <label>Otros ingresos (S/)</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="otros_ingresos"
                    value="{{ old('otros_ingresos', 0) }}"
                >
            </div>

            <div class="campo-formulario">
                <label>Ventas de lubricantes (S/)</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="ventas_lubricantes"
                    value="{{ old('ventas_lubricantes', 0) }}"
                >
            </div>
        </div>
    </section>

    <div class="acciones-formulario acciones-persistentes">
        <a class="boton boton-secundario" href="{{ route('turnos.indice') }}">
            Cancelar
        </a>

        <button class="boton boton-primario" type="submit">
            <x-icono nombre="guardar" tamano="18"/>
            {{ $modoRegistro === 'extraordinario'
                ? 'Registrar corte extraordinario'
                : 'Registrar turno' }}
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.fila-totalizador').forEach(function (fila) {
        const lecturaAnteriorTexto = fila.dataset.lecturaAnterior;
        const lecturaFinal = fila.querySelector('.lectura-final');
        const galones = fila.querySelector('.galones-calculados');

        function calcular() {
            if (
                !lecturaFinal
                || !galones
                || lecturaAnteriorTexto === ''
                || lecturaFinal.value === ''
            ) {
                if (galones) {
                    galones.value = '';
                }
                return;
            }

            const anterior = Number(lecturaAnteriorTexto);
            const final = Number(lecturaFinal.value);

            if (
                !Number.isFinite(anterior)
                || !Number.isFinite(final)
            ) {
                galones.value = '';
                return;
            }

            if (final < anterior) {
                galones.value = '';
                lecturaFinal.setCustomValidity(
                    'La lectura final no puede ser menor que la lectura anterior.'
                );
                return;
            }

            lecturaFinal.setCustomValidity('');
            galones.value = (final - anterior).toFixed(3);
        }

        if (lecturaFinal) {
            lecturaFinal.addEventListener('input', calcular);
            calcular();
        }
    });
});
</script>

@endsection
