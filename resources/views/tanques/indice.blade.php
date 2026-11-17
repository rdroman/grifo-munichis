@extends('layouts.aplicacion')

@section('titulo', 'Tanques')
@section('encabezado', 'Tanques')

@section('contenido')

<div class="encabezado-pagina gm-encabezado-directo">
    <div>
        <span class="tarjeta-sobretitulo">Inventario</span>
        <h1>Control de tanques</h1>
    </div>
</div>

<div class="rejilla-tanques gm-rejilla-tanques-compacta">
    @foreach($tanques as $t)
        @php
            $u = $ultimos->get($t->id);
            $med = $u && $columnas['medido'] ? $u->{$columnas['medido']} : null;
            $cap = $t->capacidad_gal ?? $t->capacidad_galones ?? null;
            $nombreTanque = trim((string) ($t->nombre ?? ''));
            $nombreProducto = trim((string) ($t->producto ?? ''));
        @endphp

        <article class="tarjeta tanque-resumen gm-tanque-resumen">
            <div class="gm-tanque-identidad">
                <span class="insignia estado-activo">{{ $t->producto_codigo }}</span>
                <h2>{{ $nombreProducto ?: $nombreTanque }}</h2>
            </div>

            <strong class="gm-tanque-saldo">
                {{ $med !== null ? number_format((float) $med, 3) . ' gal' : 'Sin medición' }}
            </strong>

            @if($cap)
                <div class="barra-progreso">
                    <span style="width:{{ min(100, max(0, ((float) ($med ?? 0) / (float) $cap) * 100)) }}%"></span>
                </div>
                <small>Capacidad: {{ number_format((float) $cap, 0) }} gal</small>
            @endif
        </article>
    @endforeach
</div>

@if(in_array(auth()->user()->rol, ['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR'], true))
<section class="tarjeta gm-tarjeta-compacta">
    <div class="tarjeta-cabecera">
        <div>
            <span class="tarjeta-sobretitulo" id="modo-control-tanque">Nuevo control</span>
            <h2 id="titulo-control-tanque">Registrar medición diaria</h2>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('tanques.guardar') }}"
        class="gm-form-tanque-compacto"
        id="form-control-tanque"
    >
        @csrf

        <div class="campo-formulario">
            <label for="tanque_id">Tanque *</label>
            <select id="tanque_id" name="tanque_id" required>
                @foreach($tanques as $t)
                    <option value="{{ $t->id }}" @selected(old('tanque_id', session('tanques_tanque_id')) == $t->id)>
                        {{ $t->producto_codigo }} · {{ $t->producto }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="campo-formulario">
            <label for="fecha_control">Fecha *</label>
            <input
                id="fecha_control"
                type="date"
                name="fecha_control"
                value="{{ old('fecha_control', session('tanques_fecha_control', now()->toDateString())) }}"
                required
            >
        </div>

        <div class="campo-formulario">
            <label for="saldo_anterior">Saldo anterior</label>
            <input id="saldo_anterior" type="number" step="0.0001" readonly>
        </div>

        <div class="campo-formulario">
            <label for="galones_ingreso">Ingreso</label>
            <input id="galones_ingreso" type="number" step="0.0001" readonly>
        </div>

        <div class="campo-formulario">
            <label for="salida_total_visual">Salidas</label>
            <input id="salida_total_visual" type="number" step="0.001" readonly>
        </div>

        <div class="campo-formulario">
            <label for="saldo_teorico_galones">Saldo teórico</label>
            <input id="saldo_teorico_galones" type="number" step="0.0001" readonly>
        </div>

        <div class="campo-formulario">
            <label for="saldo_medido_galones">Saldo medido *</label>
            <input
                id="saldo_medido_galones"
                type="number"
                min="0"
                step="0.001"
                name="saldo_medido_galones"
                value="{{ old('saldo_medido_galones') }}"
                required
            >
        </div>

        <div class="campo-formulario">
            <label for="diferencia_galones">Diferencia</label>
            <input id="diferencia_galones" type="number" step="0.0001" readonly>
        </div>

        <details class="gm-desglose-salidas">
            <summary>Desglose de salidas</summary>
            <div class="gm-desglose-salidas-grid">
                <div class="campo-formulario">
                    <label for="galones_venta_normal">Venta normal</label>
                    <input id="galones_venta_normal" type="number" step="0.001" readonly>
                </div>
                <div class="campo-formulario">
                    <label for="galones_descuento">Descuentos</label>
                    <input id="galones_descuento" type="number" step="0.001" readonly>
                </div>
                <div class="campo-formulario">
                    <label for="galones_credito">Créditos</label>
                    <input id="galones_credito" type="number" step="0.001" readonly>
                </div>
            </div>
        </details>

        <p id="estado-control-tanque" role="status" aria-live="polite" style="margin:0 0 8px;font-size:.85rem;">
            Consultando medición para el tanque y fecha seleccionados…
        </p>
        <div class="gm-acciones-tanque">
            <button class="boton boton-primario" type="submit" id="guardar-control-tanque" disabled>Guardar control</button>
        </div>
    </form>
</section>
<section class="tarjeta gm-tarjeta-compacta">
    <details>
        <summary style="cursor:pointer;font-weight:600;">Registrar devolución al tanque</summary>
        <form method="POST" action="{{ route('tanques.devolucion.guardar') }}" style="display:flex;flex-wrap:wrap;gap:12px;margin-top:12px;align-items:end;" onsubmit="return confirm('¿Confirmar el retorno físico del combustible a este tanque? Esta operación modificará el inventario teórico.');">
            @csrf
            <div class="campo-formulario">
                <label for="devolucion_tanque_id">Tanque *</label>
                <select id="devolucion_tanque_id" name="devolucion_tanque_id" required>
                    @foreach($tanques as $t)
                        <option value="{{ $t->id }}" @selected(old('devolucion_tanque_id') == $t->id)>{{ $t->producto_codigo }} · {{ $t->producto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="campo-formulario">
                <label for="devolucion_fecha">Fecha del turno *</label>
                <input id="devolucion_fecha" name="devolucion_fecha" type="date" value="{{ old('devolucion_fecha', now()->toDateString()) }}" required>
            </div>
            <div class="campo-formulario">
                <label for="devolucion_tipo_turno_id">Turno *</label>
                <select id="devolucion_tipo_turno_id" name="devolucion_tipo_turno_id" required>
                    @foreach($tiposTurno as $tipo)
                        <option value="{{ $tipo->id }}" @selected(old('devolucion_tipo_turno_id') == $tipo->id)>{{ $tipo->codigo }} · {{ $tipo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="campo-formulario">
                <label for="devolucion_galones">Galones devueltos *</label>
                <input id="devolucion_galones" name="devolucion_galones" type="number" min="0.0001" step="0.0001" value="{{ old('devolucion_galones') }}" required>
            </div>
            <div class="campo-formulario" style="min-width:180px;flex:1;">
                <label for="devolucion_motivo">Motivo *</label>
                <input id="devolucion_motivo" name="devolucion_motivo" type="text" minlength="5" maxlength="255" value="{{ old('devolucion_motivo') }}" required placeholder="Motivo de la devolución">
            </div>
            <button type="submit" class="boton boton-primario">Registrar devolución</button>
        </form>
        @if($errors->has('devolucion_tipo_turno_id'))
            <p class="texto-peligro">{{ $errors->first('devolucion_tipo_turno_id') }}</p>
        @endif
    </details>
</section>
@endif

<section class="tarjeta tabla-tarjeta gm-tarjeta-compacta">
    <div class="tarjeta-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Histórico</span>
            <h2>Controles diarios</h2>
        </div>

        <form method="GET" class="filtro-fechas-mini">
            <input type="date" name="desde" value="{{ $desde }}">
            <input type="date" name="hasta" value="{{ $hasta }}">
            <button class="boton boton-secundario boton-compacto">Aplicar</button>
        </form>
    </div>

    <div class="tabla-responsive">
        <table class="tabla-datos gm-tabla-tanques">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tanque</th>
                    <th class="numero">Ingreso</th>
                    <th class="numero">Salida</th>
                    <th class="numero">Teórico</th>
                    <th class="numero">Medido</th>
                    <th class="numero">Diferencia</th>
                </tr>
            </thead>

            <tbody>
                @forelse($historial as $r)
                    @php
                        $venta = $columnas['venta'] ? (float) $r->{$columnas['venta']} : 0;
                        $descuento = $columnas['descuento'] ? (float) $r->{$columnas['descuento']} : 0;
                        $credito = $columnas['credito'] ? (float) $r->{$columnas['credito']} : 0;
                    @endphp
                    <tr>
                        <td>
                            {{ $columnas['fecha'] ? \Carbon\Carbon::parse($r->{$columnas['fecha']})->format('d/m/Y') : '—' }}
                        </td>
                        <td>
                            <strong>{{ $r->producto_codigo }}</strong>
                            <span>{{ $r->producto }}</span>
                        </td>
                        <td class="numero">
                            {{ $columnas['ingreso'] ? number_format((float) $r->{$columnas['ingreso']}, 4) : '—' }}
                        </td>
                        <td class="numero">
                            <strong>{{ $columnas['salida'] ? number_format((float) $r->{$columnas['salida']}, 3) : '—' }}</strong>
                            <small class="bloque gm-salida-detalle">
                                Normal {{ number_format($venta, 3) }} · Desc. {{ number_format($descuento, 3) }} · Crédito {{ number_format($credito, 3) }}
                            </small>
                        </td>
                        <td class="numero">
                            {{ $columnas['teorico'] ? number_format((float) $r->{$columnas['teorico']}, 4) : '—' }}
                        </td>
                        <td class="numero">
                            {{ $columnas['medido'] && $r->{$columnas['medido']} !== null ? number_format((float) $r->{$columnas['medido']}, 3) : '—' }}
                        </td>
                        <td class="numero {{ $columnas['diferencia'] && $r->{$columnas['diferencia']} !== null && (float) $r->{$columnas['diferencia']} < 0 ? 'texto-peligro' : '' }}">
                            {{ $columnas['diferencia'] && $r->{$columnas['diferencia']} !== null ? number_format((float) $r->{$columnas['diferencia']}, 4) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="tabla-vacia">No hay controles en el período seleccionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($historial->hasPages())
        <div class="paginacion-simple">
            <div>
                Mostrando {{ $historial->firstItem() }}–{{ $historial->lastItem() }}
                de {{ $historial->total() }}
            </div>
            <div class="paginacion-acciones">
                @if(!$historial->onFirstPage())
                    <a class="boton boton-secundario boton-compacto" href="{{ $historial->previousPageUrl() }}">Anterior</a>
                @endif
                <span class="paginacion-pagina">
                    Página {{ $historial->currentPage() }} de {{ $historial->lastPage() }}
                </span>
                @if($historial->hasMorePages())
                    <a class="boton boton-secundario boton-compacto" href="{{ $historial->nextPageUrl() }}">Siguiente</a>
                @endif
            </div>
        </div>
    @endif
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.getElementById('form-control-tanque');
    const tanque = document.getElementById('tanque_id');
    const fecha = document.getElementById('fecha_control');
    if (!formulario || !tanque || !fecha) return;

    const saldoAnterior = document.getElementById('saldo_anterior');
    const ingreso = document.getElementById('galones_ingreso');
    const venta = document.getElementById('galones_venta_normal');
    const descuento = document.getElementById('galones_descuento');
    const credito = document.getElementById('galones_credito');
    const salidaTotal = document.getElementById('salida_total_visual');
    const teorico = document.getElementById('saldo_teorico_galones');
    const medido = document.getElementById('saldo_medido_galones');
    const diferencia = document.getElementById('diferencia_galones');
    const boton = document.getElementById('guardar-control-tanque');
    const estado = document.getElementById('estado-control-tanque');
    const modo = document.getElementById('modo-control-tanque');
    const titulo = document.getElementById('titulo-control-tanque');
    const urlCalculo = @json(route('tanques.calcular'));
    let solicitud = null;
    let secuencia = 0;
    let listo = false;

    function formato(valor) {
        if (valor === null || valor === undefined || valor === '') return '';
        const numero = Number(valor);
        return Number.isFinite(numero) ? numero.toFixed(3) : '';
    }

    function formato4(valor) {
        if (valor === null || valor === undefined || valor === '') return '';
        const numero = Number(valor);
        return Number.isFinite(numero) ? numero.toFixed(4) : '';
    }

    function calcularDiferencia() {
        if (medido.value === '' || teorico.value === '') {
            diferencia.value = '';
            return;
        }
        const valor = Number(medido.value) - Number(teorico.value);
        diferencia.value = Number.isFinite(valor) ? valor.toFixed(4) : '';
    }

    function limpiar() {
        [saldoAnterior, ingreso, venta, descuento, credito, salidaTotal, teorico, medido, diferencia]
            .forEach(campo => { campo.value = ''; });
    }

    async function cargarCalculo() {
        const actual = ++secuencia;
        if (solicitud) solicitud.abort();
        listo = false;
        boton.disabled = true;
        medido.disabled = true;
        limpiar();
        modo.textContent = 'Consultando control';
        titulo.textContent = 'Medición diaria';
        estado.classList.remove('texto-peligro');

        if (!tanque.value || !fecha.value) {
            estado.textContent = 'Seleccione el tanque y una fecha válida.';
            return;
        }

        estado.textContent = 'Consultando medición registrada…';
        solicitud = new AbortController();
        const url = new URL(urlCalculo, window.location.origin);
        url.searchParams.set('tanque_id', tanque.value);
        url.searchParams.set('fecha_control', fecha.value);

        try {
            const respuesta = await fetch(url.toString(), {
                signal: solicitud.signal,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            if (!respuesta.ok) throw new Error('Error HTTP ' + respuesta.status);
            const datos = await respuesta.json();
            if (actual !== secuencia) return;

            saldoAnterior.value = formato4(datos.saldo_anterior);
            ingreso.value = formato4(datos.ingreso);
            venta.value = formato(datos.venta_normal);
            descuento.value = formato(datos.descuentos);
            credito.value = formato(datos.creditos);
            salidaTotal.value = formato(datos.salida_total);
            teorico.value = formato4(datos.saldo_teorico);
            medido.value = formato(datos.saldo_medido_galones);
            calcularDiferencia();

            if (datos.control_existente) {
                modo.textContent = 'Control existente';
                titulo.textContent = 'Actualizar medición diaria';
                boton.textContent = 'Actualizar control';
                estado.textContent = datos.saldo_medido_galones === null
                    ? 'Existe control sin medición física. Registre el saldo medido para completar el día.'
                    : 'Se recuperó la medición registrada. Puede corregir el saldo medido y guardar.';
            } else {
                modo.textContent = 'Nuevo control';
                titulo.textContent = 'Registrar medición diaria';
                boton.textContent = 'Guardar control';
                estado.textContent = 'No existe medición para esta fecha; puede registrar una nueva.';
            }

            listo = true;
            medido.disabled = false;
            boton.disabled = false;
        } catch (error) {
            if (actual !== secuencia || error.name === 'AbortError') return;
            console.error('No se pudo consultar el control de tanque:', error);
            modo.textContent = 'Consulta fallida';
            boton.textContent = 'Guardar control';
            estado.textContent = 'No se pudo cargar el control. Compruebe la conexión y vuelva a seleccionar la fecha.';
            estado.classList.add('texto-peligro');
            // Se bloquea el guardado para evitar sobrescribir otra medición.
        }
    }

    tanque.addEventListener('change', cargarCalculo);
    fecha.addEventListener('change', cargarCalculo);
    medido.addEventListener('input', calcularDiferencia);
    formulario.addEventListener('submit', (evento) => {
        if (!listo) {
            evento.preventDefault();
            return;
        }
        listo = false;
        boton.disabled = true;
        boton.textContent = 'Guardando…';
    });

    cargarCalculo();
});
</script>

@endsection
