@extends('layouts.aplicacion')

@section('titulo', 'Modificar turno')
@section('encabezado', 'Modificar turno')

@section('contenido')

<div class="encabezado-pagina">
    <a href="{{ route('turnos.mostrar', $turnoObj->id) }}" class="enlace-volver">
        <x-icono nombre="volver" tamano="17"/> Turno
    </a>
    <h1>Modificar turno operativo</h1>
    <p class="texto-secundario">
        Se recalculan automáticamente los galones, los montos del turno y, cuando corresponde,
        los galones del siguiente corte de cada manguera.
    </p>
</div>

@if($errors->any())
    <div class="alerta alerta-error">
        <x-icono nombre="alerta"/>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

@if($esOperador)
    <div class="alerta alerta-advertencia" style="margin-bottom:16px;">
        <x-icono nombre="alerta"/>
        <span>
            Esta es la única modificación permitida para este turno como operador.
            Al guardar, no podrás volver a editarlo. La edición solo está permitida durante el mismo día operativo.
        </span>
    </div>
@endif

<form method="POST" action="{{ route('turnos.actualizar', $turnoObj->id) }}" class="formulario-modulo">
    @csrf
    @method('PATCH')

    <section class="tarjeta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Cabecera</span>
                <h2>Datos del turno</h2>
            </div>
        </div>

        <div class="rejilla-campos">
            <div class="campo-formulario">
                <label>Fecha</label>
                <input type="date" value="{{ $turnoObj->fecha_operacion }}" disabled>
                <small class="texto-secundario">La fecha no se modifica desde esta pantalla para preservar la secuencia real de cortes.</small>
            </div>

            <div class="campo-formulario">
                <label>Tipo de turno *</label>
                @php
                    $tipoActual = $tipos->firstWhere('id', $turnoObj->tipo_turno_id);
                @endphp
                <input
                    value="{{ $tipoActual?->codigo }} · {{ $tipoActual?->nombre }}"
                    disabled
                >
                <input
                    type="hidden"
                    name="tipo_turno_id"
                    value="{{ $turnoObj->tipo_turno_id }}"
                >
                <small class="texto-secundario">
                    El tipo no puede modificarse porque forma parte de la secuencia operativa.
                </small>
            </div>

            <div class="campo-formulario">
                <label>Trabajador</label>
                @if($esOperador)
                    @php $trabajadorPropio = $trabajadores->first(); @endphp
                    <input value="{{ $trabajadorPropio?->nombre_completo ?? 'Sin trabajador vinculado' }}" disabled>
                    <input type="hidden" name="trabajador_id" value="{{ $trabajadorPropio?->id }}">
                @else
                    <select name="trabajador_id">
                        <option value="">Seleccione</option>
                        @foreach($trabajadores as $t)
                            <option value="{{ $t->id }}" @selected(old('trabajador_id', $turnoObj->trabajador_id) == $t->id)>
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
                            <option value="{{ $m->id }}" @selected(old('motivo_turno_id', $turnoObj->motivo_turno_id) == $m->id)>
                                {{ $m->codigo ?? '' }} · {{ $m->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="campo-formulario campo-span-2">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="3">{{ old('observaciones', $turnoObj->observaciones) }}</textarea>
            </div>

            <div class="campo-formulario campo-span-2">
                <label>Motivo de la modificación *</label>
                <input
                    type="text"
                    name="motivo_edicion"
                    maxlength="500"
                    value="{{ old('motivo_edicion') }}"
                    placeholder="Indica por qué se está modificando este turno"
                    required
                >
            </div>
        </div>
    </section>

    <section class="tarjeta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Surtidores</span>
                <h2>Lecturas de totalizadores</h2>
                <p class="texto-secundario">
                    Modifica la lectura final cuando corresponda. Los galones se recalculan automáticamente.
                </p>
            </div>
        </div>

        <div class="tabla-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Manguera</th>
                        <th class="numero">Lectura anterior</th>
                        <th class="numero">Lectura final</th>
                        <th class="numero">Galones</th>
                        <th class="numero">Siguiente lectura</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mangueras as $m)
                        @php
                            $anterior = $m->lectura_anterior !== null ? (float) $m->lectura_anterior : null;
                            $actual = $m->lectura_actual !== null ? (float) $m->lectura_actual : null;
                            $siguiente = $m->lectura_siguiente !== null ? (float) $m->lectura_siguiente : null;
                            $valorFinal = old('lecturas.' . $m->id . '.lectura', $actual);
                            $reinicioEnTurno = (bool) ($m->reinicio_en_turno ?? false);
                            $siguienteTieneReinicio = (bool) ($m->siguiente_tiene_reinicio ?? false);
                        @endphp

                        <tr
                            class="fila-totalizador-edicion"
                            data-anterior="{{ $anterior !== null ? number_format($anterior, 3, '.', '') : '' }}"
                        >
                            <td>
                                <strong>{{ $m->producto_codigo }}</strong>
                                <small class="bloque">{{ $m->producto }}</small>
                            </td>

                            <td>
                                <strong>{{ $m->codigo }}</strong>
                                @if($m->lado)
                                    <small class="bloque">{{ $m->lado }}</small>
                                @endif
                            </td>

                            <td class="numero">
                                @if($reinicioEnTurno)
                                    <strong>0.000</strong>
                                    <small class="bloque texto-secundario">Base por reinicio</small>
                                @elseif($anterior !== null)
                                    {{ number_format($anterior, 3, '.', ',') }}
                                    @if($m->fecha_lectura_anterior)
                                        <small class="bloque">{{ \Carbon\Carbon::parse($m->fecha_lectura_anterior)->format('d/m/Y') }}</small>
                                    @endif
                                @else
                                    <span class="texto-secundario">Lectura inicial</span>
                                @endif
                            </td>

                            <td>
                                <input
                                    class="input-tabla lectura-final-edicion"
                                    type="number"
                                    step="0.001"
                                    name="lecturas[{{ $m->id }}][lectura]"
                                    value="{{ $valorFinal }}"
                                    @if($anterior !== null) min="{{ number_format($anterior, 3, '.', '') }}" @else readonly @endif
                                    @if($siguiente !== null && ! $siguienteTieneReinicio) max="{{ number_format($siguiente, 3, '.', '') }}" @endif
                                    required
                                >
                            </td>

                            <td>
                                <input
                                    class="input-tabla galones-edicion"
                                    type="number"
                                    step="0.001"
                                    value="{{ $m->galones_actuales !== null ? number_format((float)$m->galones_actuales, 3, '.', '') : '' }}"
                                    readonly
                                    tabindex="-1"
                                >
                            </td>

                            <td class="numero">
                                @if($siguiente !== null)
                                    {{ number_format($siguiente, 3, '.', ',') }}
                                    @if($siguienteTieneReinicio)
                                        <small class="bloque texto-secundario">
                                            Siguiente corte inicia desde 0.000
                                        </small>
                                    @elseif($m->fecha_lectura_siguiente)
                                        <small class="bloque">{{ \Carbon\Carbon::parse($m->fecha_lectura_siguiente)->format('d/m/Y') }}</small>
                                    @endif
                                @else
                                    <span class="texto-secundario">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="acciones-formulario acciones-persistentes">
        <a class="boton boton-secundario" href="{{ route('turnos.mostrar', $turnoObj->id) }}">Cancelar</a>
        <button class="boton boton-primario" type="submit">
            <x-icono nombre="guardar" tamano="18"/> Guardar modificación
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.fila-totalizador-edicion').forEach(function (fila) {
        const anteriorTexto = fila.dataset.anterior;
        const finalInput = fila.querySelector('.lectura-final-edicion');
        const galonesInput = fila.querySelector('.galones-edicion');

        function recalcular() {
            if (!finalInput || !galonesInput || anteriorTexto === '' || finalInput.value === '') {
                return;
            }

            const anterior = Number(anteriorTexto);
            const final = Number(finalInput.value);

            if (!Number.isFinite(anterior) || !Number.isFinite(final) || final < anterior) {
                galonesInput.value = '';
                return;
            }

            galonesInput.value = (final - anterior).toFixed(3);
        }

        if (finalInput && !finalInput.readOnly) {
            finalInput.addEventListener('input', recalcular);
            recalcular();
        }
    });
});
</script>

@endsection
