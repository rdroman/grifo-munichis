@extends('layouts.aplicacion')

@section('titulo', 'Nueva venta a crédito')
@section('encabezado', 'Créditos')

@section('contenido')
@php
    // [PARCHE_RETORNO_TURNO_V2] contexto creditos
    $volverTurnoId = (int) old('volver_turno_id', request()->integer('turno_operativo_id'));
    $volverAlTurno = (true) && $volverTurnoId > 0;
@endphp
<div class="encabezado-pagina">
    <a class="enlace-volver" href="{{ route('creditos.indice') }}">
        <x-icono nombre="volver" tamano="17"/> Créditos
    </a>
    <h1>Nueva venta a crédito</h1>
</div>

@if($errors->any())
    <div class="alerta alerta-error">
        <x-icono nombre="alerta"/>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

<form method="POST" action="{{ route('creditos.guardar') }}" class="formulario-modulo gm-formulario-compacto" id="form-credito">
    @csrf
    @if($volverAlTurno)<input type="hidden" name="volver_turno_id" value="{{ $volverTurnoId }}">@endif

    <section class="tarjeta gm-tarjeta-compacta">
        <div class="rejilla-campos">
            <div class="campo-formulario">
                <label>Cliente *</label>
                <select name="cliente_id" required>
                    <option value="">Seleccione</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected(old('cliente_id') == $cliente->id)>
                            {{ $cliente->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="campo-formulario">
                <label>Fecha y turno *</label>
                <select name="turno_operativo_id" id="turno_operativo_id" required>
                    <option value="">Seleccione el turno</option>
                    @foreach($turnos as $turno)
                        <option
                            value="{{ $turno->id }}"
                            data-fecha="{{ $turno->fecha_operacion }}"
                            @selected(old('turno_operativo_id', $turnoPreseleccionado ?? null) == $turno->id)
                        >
                            {{ $turno->fecha_operacion }} · {{ $turno->codigo ?? 'Turno' }}
                            @if($turno->trabajador)
                                · {{ $turno->trabajador }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="campo-formulario">
                <label>Número de vale</label>
                <input name="numero_vale" value="{{ old('numero_vale') }}">
            </div>

            <div class="campo-formulario campo-span-2">
                <label>Observaciones</label>
                <input name="observaciones" value="{{ old('observaciones') }}">
            </div>
        </div>
    </section>

    <section class="tarjeta gm-tarjeta-compacta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Detalle</span>
                <h2>Combustibles</h2>
            </div>
            <div>
                <strong>Total: S/ <span id="total-credito">0.00</span></strong>
            </div>
        </div>

        <div class="tabla-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad (gal)</th>
                        <th>Precio por galón</th>
                        <th>Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productos as $i => $producto)
                        <tr data-fila-item="{{ $i }}">
                            <td>
                                <label class="fila-check">
                                    <input
                                        type="checkbox"
                                        data-item-check="{{ $i }}"
                                        data-producto-id="{{ $producto->id }}"
                                    >
                                    <strong>{{ $producto->codigo }}</strong> · {{ $producto->nombre }}
                                </label>
                                <input
                                    type="hidden"
                                    name="items[{{ $i }}][producto_id]"
                                    data-prod="{{ $i }}"
                                    disabled
                                    value="{{ $producto->id }}"
                                >
                            </td>
                            <td>
                                <input
                                    class="input-tabla"
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    name="items[{{ $i }}][cantidad]"
                                    data-cantidad="{{ $i }}"
                                    disabled
                                    placeholder="0.0000"
                                >
                            </td>
                            <td>
                                <div style="display:flex; gap:8px; align-items:center; min-width:210px;">
                                    <span style="font-weight:700;">S/</span>
                                    <input
                                        class="input-tabla"
                                        type="number"
                                        min="0.0001"
                                        max="9999.9999"
                                        step="0.01"
                                        data-precio="{{ $i }}"
                                        value=""
                                        readonly
                                        tabindex="-1"
                                        aria-label="Precio por galón"
                                        style="min-width:95px;"
                                    >

                                    @if($puedeModificarPrecio)
                                        <button
                                            type="button"
                                            class="boton boton-secundario boton-compacto"
                                            data-editar-precio="{{ $i }}"
                                            disabled
                                            style="padding:8px 10px; white-space:nowrap;"
                                        >
                                            Modificar
                                        </button>

                                        <input
                                            type="hidden"
                                            name="items[{{ $i }}][precio_manual]"
                                            data-precio-manual="{{ $i }}"
                                            disabled
                                        >
                                    @endif
                                </div>
                                <small data-precio-estado="{{ $i }}"></small>
                            </td>
                            <td>
                                <strong>S/ <span data-importe="{{ $i }}">0.00</span></strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="acciones-formulario">
        <a class="boton boton-secundario" href="{{ route('creditos.indice') }}">Cancelar</a>
        <button class="boton boton-primario" type="submit" id="btn-guardar-credito">
            <x-icono nombre="guardar" tamano="18"/> Guardar venta
        </button>
    </div>
</form>

@if($volverAlTurno)
<script>
document.addEventListener('DOMContentLoaded', function () {
    // [PARCHE_RETORNO_TURNO_V2] navegacion creditos
    const destinoTurno = @json(route('turnos.mostrar', $volverTurnoId));

    document.querySelectorAll('a').forEach(function (enlace) {
        const texto = (enlace.textContent || '').trim().toLowerCase();
        if (texto.includes('volver') || texto.includes('cancelar')) {
            enlace.setAttribute('href', destinoTurno);
        }
    });

    document.querySelectorAll('button[type="button"], button:not([type])').forEach(function (boton) {
        const texto = (boton.textContent || '').trim().toLowerCase();
        if (texto.includes('volver') || texto.includes('cancelar')) {
            boton.addEventListener('click', function (evento) {
                evento.preventDefault();
                window.location.assign(destinoTurno);
            });
        }
    });
});
</script>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const turno = document.getElementById('turno_operativo_id');
    const fechaVisual = document.getElementById('fecha_venta_visual');
    const totalCredito = document.getElementById('total-credito');
    const formulario = document.getElementById('form-credito');
    const urlPrecio = @json(route('creditos.precio-vigente'));
    const puedeModificarPrecio = @json($puedeModificarPrecio);

    // Precio que finalmente se usa para mostrar el importe.
    const preciosAplicados = new Map();
    // Precio determinado por el historial vigente del turno.
    const preciosAutomaticos = new Map();

    function actualizarFecha() {
        if (!fechaVisual) {
            return;
        }

        const opcion = turno.options[turno.selectedIndex];
        fechaVisual.value = opcion?.dataset?.fecha || '';
    }

    function recalcularFila(i) {
        const cantidad = document.querySelector(`[data-cantidad="${i}"]`);
        const importe = document.querySelector(`[data-importe="${i}"]`);
        const precio = preciosAplicados.get(String(i));
        const galones = parseFloat(cantidad?.value || '0');
        const total = precio && galones > 0 ? precio * galones : 0;
        importe.textContent = total.toFixed(2);
        recalcularTotal();
    }

    function recalcularTotal() {
        let total = 0;
        document.querySelectorAll('[data-importe]').forEach(el => {
            total += parseFloat(el.textContent || '0') || 0;
        });
        totalCredito.textContent = total.toFixed(2);
    }

    function restaurarPrecioAutomatico(i) {
        const automatico = preciosAutomaticos.get(String(i));
        const inputPrecio = document.querySelector(`[data-precio="${i}"]`);
        const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);
        const boton = document.querySelector(`[data-editar-precio="${i}"]`);
        const estado = document.querySelector(`[data-precio-estado="${i}"]`);

        if (automatico === undefined) {
            return;
        }

        preciosAplicados.set(String(i), automatico);
        inputPrecio.value = automatico.toFixed(2);
        inputPrecio.readOnly = true;
        inputPrecio.tabIndex = -1;

        if (inputManual) {
            inputManual.value = '';
            inputManual.disabled = true;
        }

        if (boton) {
            boton.textContent = 'Modificar';
        }

        estado.textContent = '';
        recalcularFila(i);
    }

    async function obtenerPrecio(i, productoId) {
        const inputPrecio = document.querySelector(`[data-precio="${i}"]`);
        const estado = document.querySelector(`[data-precio-estado="${i}"]`);
        const boton = document.querySelector(`[data-editar-precio="${i}"]`);
        const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);

        if (!turno.value) {
            inputPrecio.value = '';
            inputPrecio.placeholder = '—';
            estado.textContent = 'Seleccione primero el turno.';
            if (boton) boton.disabled = true;
            return false;
        }

        inputPrecio.value = '';
        inputPrecio.placeholder = 'Consultando...';
        inputPrecio.readOnly = true;
        estado.textContent = '';
        if (boton) boton.disabled = true;
        if (inputManual) {
            inputManual.disabled = true;
            inputManual.value = '';
        }

        const params = new URLSearchParams({
            turno_operativo_id: turno.value,
            producto_id: productoId,
        });

        try {
            const respuesta = await fetch(`${urlPrecio}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const datos = await respuesta.json();

            if (!respuesta.ok || !datos.ok) {
                throw new Error(datos.mensaje || 'No se pudo determinar el precio vigente.');
            }

            const precio = parseFloat(datos.precio);
            preciosAutomaticos.set(String(i), precio);
            preciosAplicados.set(String(i), precio);
            inputPrecio.value = precio.toFixed(2);
            inputPrecio.placeholder = '';

            const origen = `Automático: ${datos.fecha_operacion} · ${datos.turno || 'Turno'}`;
            estado.dataset.origen = origen;
            estado.textContent = '';

            if (boton && puedeModificarPrecio) {
                boton.disabled = false;
                boton.textContent = 'Modificar';
            }

            recalcularFila(i);
            return true;
        } catch (error) {
            preciosAutomaticos.delete(String(i));
            preciosAplicados.delete(String(i));
            inputPrecio.value = '';
            inputPrecio.placeholder = 'Sin precio';
            estado.textContent = error.message;
            if (boton) boton.disabled = true;
            recalcularFila(i);
            return false;
        }
    }

    async function activarFila(check) {
        const i = check.dataset.itemCheck;
        const productoId = check.dataset.productoId;
        const hiddenProducto = document.querySelector(`[data-prod="${i}"]`);
        const cantidad = document.querySelector(`[data-cantidad="${i}"]`);
        const inputPrecio = document.querySelector(`[data-precio="${i}"]`);
        const boton = document.querySelector(`[data-editar-precio="${i}"]`);
        const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);

        if (!check.checked) {
            hiddenProducto.disabled = true;
            cantidad.disabled = true;
            cantidad.value = '';
            preciosAutomaticos.delete(String(i));
            preciosAplicados.delete(String(i));
            inputPrecio.value = '';
            inputPrecio.placeholder = '—';
            inputPrecio.readOnly = true;
            document.querySelector(`[data-precio-estado="${i}"]`).textContent = '';
            document.querySelector(`[data-importe="${i}"]`).textContent = '0.00';
            if (boton) boton.disabled = true;
            if (inputManual) {
                inputManual.disabled = true;
                inputManual.value = '';
            }
            recalcularTotal();
            return;
        }

        if (!turno.value) {
            check.checked = false;
            alert('Seleccione primero la fecha y el turno de la venta.');
            turno.focus();
            return;
        }

        const ok = await obtenerPrecio(i, productoId);
        if (!ok) {
            check.checked = false;
            hiddenProducto.disabled = true;
            cantidad.disabled = true;
            return;
        }

        hiddenProducto.disabled = false;
        cantidad.disabled = false;
        cantidad.focus();
    }

    document.querySelectorAll('[data-item-check]').forEach(check => {
        check.addEventListener('change', () => activarFila(check));
    });

    document.querySelectorAll('[data-cantidad]').forEach(input => {
        input.addEventListener('input', () => recalcularFila(input.dataset.cantidad));
    });

    if (puedeModificarPrecio) {
        document.querySelectorAll('[data-editar-precio]').forEach(boton => {
            boton.addEventListener('click', () => {
                const i = boton.dataset.editarPrecio;
                const inputPrecio = document.querySelector(`[data-precio="${i}"]`);
                const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);
                const estado = document.querySelector(`[data-precio-estado="${i}"]`);

                if (inputPrecio.readOnly) {
                    inputPrecio.readOnly = false;
                    inputPrecio.tabIndex = 0;
                    inputManual.disabled = false;
                    inputManual.value = inputPrecio.value;
                    boton.textContent = 'Usar automático';
                    estado.textContent = `Modificación manual habilitada · ${estado.dataset.origen || ''}`;
                    inputPrecio.focus();
                    inputPrecio.select();
                    return;
                }

                restaurarPrecioAutomatico(i);
            });
        });

        document.querySelectorAll('[data-precio]').forEach(inputPrecio => {
            inputPrecio.addEventListener('input', () => {
                if (inputPrecio.readOnly) {
                    return;
                }

                const i = inputPrecio.dataset.precio;
                const valor = parseFloat(inputPrecio.value || '0');
                const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);
                const estado = document.querySelector(`[data-precio-estado="${i}"]`);
                const automatico = preciosAutomaticos.get(String(i));

                inputManual.value = inputPrecio.value;

                if (valor > 0) {
                    preciosAplicados.set(String(i), valor);
                    estado.textContent = `Precio modificado por administrador · Automático S/ ${(automatico || 0).toFixed(2)}`;
                } else {
                    preciosAplicados.delete(String(i));
                    estado.textContent = 'Ingrese un precio mayor que cero.';
                }

                recalcularFila(i);
            });
        });
    }

    turno.addEventListener('change', async () => {
        actualizarFecha();

        // Cambiar de turno siempre invalida cualquier modificación manual previa.
        for (const check of document.querySelectorAll('[data-item-check]:checked')) {
            await obtenerPrecio(check.dataset.itemCheck, check.dataset.productoId);
        }
    });

    formulario.addEventListener('submit', (event) => {
        if (!turno.value) {
            event.preventDefault();
            alert('Seleccione el turno operativo de la venta.');
            turno.focus();
            return;
        }

        const seleccionados = document.querySelectorAll('[data-item-check]:checked');
        if (seleccionados.length === 0) {
            event.preventDefault();
            alert('Seleccione al menos un combustible.');
            return;
        }

        for (const check of seleccionados) {
            const i = check.dataset.itemCheck;
            const cantidad = parseFloat(document.querySelector(`[data-cantidad="${i}"]`).value || '0');
            const precio = preciosAplicados.get(String(i));

            if (!(cantidad > 0) || !(precio > 0)) {
                event.preventDefault();
                alert('Todos los combustibles seleccionados deben tener cantidad y un precio válido.');
                return;
            }
        }
    });

    actualizarFecha();
});
</script>
@endpush
