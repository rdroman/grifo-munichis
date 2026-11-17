@extends('layouts.aplicacion')

@section('titulo', 'Editar crédito')
@section('encabezado', 'Créditos')

@section('contenido')
@php
    $valorCliente = old('cliente_id', $venta->cliente_id);
    $valorTurno = old('turno_operativo_id', $venta->turno_operativo_id);
    $valorVale = old('numero_vale', $c['numero_vale'] ? ($venta->{$c['numero_vale']} ?? '') : '');
    $valorObservaciones = old('observaciones', $c['observaciones'] ? ($venta->{$c['observaciones']} ?? '') : '');
    $itemsOld = old('items', []);
    $esReintento = $errors->any();
@endphp

<div class="encabezado-pagina">
    <a class="enlace-volver" href="{{ route('creditos.mostrar', $venta->id) }}">
        <x-icono nombre="volver" tamano="17"/> Crédito #{{ $venta->id }}
    </a>
    <h1>Editar crédito</h1>
    <p class="texto-secundario">Solo el ADMINISTRADOR puede modificar una venta a crédito. Los cambios quedan registrados en auditoría.</p>
</div>

@if($errors->any())
    <div class="alerta alerta-error">
        <x-icono nombre="alerta"/>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

<div class="alerta" style="margin-bottom:16px; background:#fff8db; border:1px solid #f1d66a; color:#664d03;">
    <x-icono nombre="alerta"/>
    <span>
        Al cambiar el turno o un combustible, el sistema vuelve a obtener el precio vigente automáticamente.
        Si el precio guardado difiere del automático, se conserva como modificación administrativa hasta que elijas <strong>Usar automático</strong>.
    </span>
</div>

<form method="POST" action="{{ route('creditos.actualizar', $venta->id) }}" class="formulario-modulo" id="form-credito-editar">
    @csrf
    @method('PUT')

    <section class="tarjeta">
        <div class="rejilla-campos">
            <div class="campo-formulario">
                <label>Cliente *</label>
                <select name="cliente_id" required>
                    <option value="">Seleccione</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected((string)$valorCliente === (string)$cliente->id)>
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
                            @selected((string)$valorTurno === (string)$turno->id)
                        >
                            {{ $turno->fecha_operacion }} · {{ $turno->codigo ?? 'Turno' }}
                            @if($turno->trabajador)
                                · {{ $turno->trabajador }}
                            @endif
                        </option>
                    @endforeach
                </select>
                <small>La fecha de venta se obtiene del turno operativo seleccionado.</small>
            </div>

            <div class="campo-formulario">
                <label>Fecha de venta</label>
                <input type="date" id="fecha_venta_visual" readonly tabindex="-1">
            </div>

            <div class="campo-formulario">
                <label>Número de vale</label>
                <input name="numero_vale" value="{{ $valorVale }}">
            </div>

            <div class="campo-formulario campo-span-2">
                <label>Observaciones</label>
                <input name="observaciones" value="{{ $valorObservaciones }}">
            </div>
        </div>
    </section>

    <section class="tarjeta">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Detalle</span>
                <h2>Combustibles</h2>
                <p>Verifica cantidad y precio antes de guardar.</p>
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
                        @php
                            $actual = $itemsEdicion[(int)$producto->id] ?? null;
                            $oldItem = $itemsOld[$i] ?? null;
                            $seleccionado = $esReintento ? is_array($oldItem) : $actual !== null;
                            $cantidadInicial = $esReintento
                                ? (is_array($oldItem) ? ($oldItem['cantidad'] ?? '') : '')
                                : ($actual['cantidad'] ?? '');
                            $precioPreservar = $esReintento
                                ? (is_array($oldItem) ? ($oldItem['precio_manual'] ?? '') : '')
                                : ($actual['precio'] ?? '');
                        @endphp
                        <tr data-fila-item="{{ $i }}">
                            <td>
                                <label class="fila-check">
                                    <input
                                        type="checkbox"
                                        data-item-check="{{ $i }}"
                                        data-producto-id="{{ $producto->id }}"
                                        data-precio-guardado="{{ $precioPreservar }}"
                                        @checked($seleccionado)
                                    >
                                    <strong>{{ $producto->codigo }}</strong> · {{ $producto->nombre }}
                                    @if(!(bool)$producto->activo)
                                        <small>(histórico)</small>
                                    @endif
                                </label>
                                <input
                                    type="hidden"
                                    name="items[{{ $i }}][producto_id]"
                                    data-prod="{{ $i }}"
                                    value="{{ $producto->id }}"
                                    @disabled(!$seleccionado)
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
                                    value="{{ $cantidadInicial }}"
                                    @disabled(!$seleccionado)
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
                                    <button
                                        type="button"
                                        class="boton boton-secundario"
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
        <a class="boton boton-secundario" href="{{ route('creditos.mostrar', $venta->id) }}">Cancelar</a>
        <button class="boton boton-primario" type="submit">
            <x-icono nombre="guardar" tamano="18"/> Guardar cambios
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const turno = document.getElementById('turno_operativo_id');
    const fechaVisual = document.getElementById('fecha_venta_visual');
    const totalCredito = document.getElementById('total-credito');
    const formulario = document.getElementById('form-credito-editar');
    const urlPrecio = @json(route('creditos.precio-vigente'));

    const preciosAplicados = new Map();
    const preciosAutomaticos = new Map();

    function actualizarFecha() {
        const opcion = turno.options[turno.selectedIndex];
        fechaVisual.value = opcion?.dataset?.fecha || '';
    }

    function recalcularTotal() {
        let total = 0;
        document.querySelectorAll('[data-importe]').forEach(el => {
            total += parseFloat(el.textContent || '0') || 0;
        });
        totalCredito.textContent = total.toFixed(2);
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

    function aplicarManual(i, valor, automatico) {
        const inputPrecio = document.querySelector(`[data-precio="${i}"]`);
        const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);
        const boton = document.querySelector(`[data-editar-precio="${i}"]`);
        const estado = document.querySelector(`[data-precio-estado="${i}"]`);

        preciosAplicados.set(String(i), valor);
        inputPrecio.value = valor.toFixed(2);
        inputPrecio.readOnly = false;
        inputPrecio.tabIndex = 0;
        inputManual.disabled = false;
        inputManual.value = valor.toFixed(4);
        boton.textContent = 'Usar automático';
        estado.textContent = `Precio administrativo S/ ${valor.toFixed(2)} · Automático S/ ${automatico.toFixed(2)}`;
        recalcularFila(i);
    }

    function restaurarPrecioAutomatico(i) {
        const automatico = preciosAutomaticos.get(String(i));
        if (automatico === undefined) return;

        const inputPrecio = document.querySelector(`[data-precio="${i}"]`);
        const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);
        const boton = document.querySelector(`[data-editar-precio="${i}"]`);
        const estado = document.querySelector(`[data-precio-estado="${i}"]`);

        preciosAplicados.set(String(i), automatico);
        inputPrecio.value = automatico.toFixed(2);
        inputPrecio.readOnly = true;
        inputPrecio.tabIndex = -1;
        inputManual.value = '';
        inputManual.disabled = true;
        boton.textContent = 'Modificar';
        estado.textContent = estado.dataset.origen || 'Precio automático vigente.';
        recalcularFila(i);
    }

    async function obtenerPrecio(i, productoId, conservarGuardado = false) {
        const check = document.querySelector(`[data-item-check="${i}"]`);
        const inputPrecio = document.querySelector(`[data-precio="${i}"]`);
        const estado = document.querySelector(`[data-precio-estado="${i}"]`);
        const boton = document.querySelector(`[data-editar-precio="${i}"]`);
        const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);

        if (!turno.value) {
            inputPrecio.value = '';
            inputPrecio.placeholder = '—';
            estado.textContent = 'Seleccione primero el turno.';
            boton.disabled = true;
            return false;
        }

        inputPrecio.value = '';
        inputPrecio.placeholder = 'Consultando...';
        inputPrecio.readOnly = true;
        estado.textContent = '';
        boton.disabled = true;
        inputManual.disabled = true;
        inputManual.value = '';

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

            const automatico = parseFloat(datos.precio);
            preciosAutomaticos.set(String(i), automatico);
            preciosAplicados.set(String(i), automatico);
            inputPrecio.value = automatico.toFixed(2);
            inputPrecio.placeholder = '';
            inputPrecio.readOnly = true;
            inputPrecio.tabIndex = -1;

            const origen = `Automático: ${datos.fecha_operacion} · ${datos.turno || 'Turno'}`;
            estado.dataset.origen = origen;
            estado.textContent = origen;
            boton.disabled = false;
            boton.textContent = 'Modificar';

            if (conservarGuardado) {
                const guardado = parseFloat(check?.dataset?.precioGuardado || '0');
                if (guardado > 0 && Math.abs(guardado - automatico) > 0.00001) {
                    aplicarManual(i, guardado, automatico);
                    return true;
                }
            }

            recalcularFila(i);
            return true;
        } catch (error) {
            preciosAutomaticos.delete(String(i));
            preciosAplicados.delete(String(i));
            inputPrecio.value = '';
            inputPrecio.placeholder = 'Sin precio';
            estado.textContent = error.message;
            boton.disabled = true;
            recalcularFila(i);
            return false;
        }
    }

    async function activarFila(check, conservarGuardado = false, enfocar = true) {
        const i = check.dataset.itemCheck;
        const productoId = check.dataset.productoId;
        const hiddenProducto = document.querySelector(`[data-prod="${i}"]`);
        const cantidad = document.querySelector(`[data-cantidad="${i}"]`);
        const inputPrecio = document.querySelector(`[data-precio="${i}"]`);
        const boton = document.querySelector(`[data-editar-precio="${i}"]`);
        const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);
        const estado = document.querySelector(`[data-precio-estado="${i}"]`);

        if (!check.checked) {
            hiddenProducto.disabled = true;
            cantidad.disabled = true;
            cantidad.value = '';
            preciosAutomaticos.delete(String(i));
            preciosAplicados.delete(String(i));
            inputPrecio.value = '';
            inputPrecio.placeholder = '—';
            inputPrecio.readOnly = true;
            estado.textContent = '';
            document.querySelector(`[data-importe="${i}"]`).textContent = '0.00';
            boton.disabled = true;
            inputManual.disabled = true;
            inputManual.value = '';
            recalcularTotal();
            return;
        }

        if (!turno.value) {
            check.checked = false;
            alert('Seleccione primero la fecha y el turno de la venta.');
            turno.focus();
            return;
        }

        const ok = await obtenerPrecio(i, productoId, conservarGuardado);
        if (!ok) {
            check.checked = false;
            hiddenProducto.disabled = true;
            cantidad.disabled = true;
            return;
        }

        hiddenProducto.disabled = false;
        cantidad.disabled = false;
        if (enfocar) cantidad.focus();
    }

    document.querySelectorAll('[data-item-check]').forEach(check => {
        check.addEventListener('change', () => activarFila(check, false, true));
    });

    document.querySelectorAll('[data-cantidad]').forEach(input => {
        input.addEventListener('input', () => recalcularFila(input.dataset.cantidad));
    });

    document.querySelectorAll('[data-editar-precio]').forEach(boton => {
        boton.addEventListener('click', () => {
            const i = boton.dataset.editarPrecio;
            const inputPrecio = document.querySelector(`[data-precio="${i}"]`);
            const inputManual = document.querySelector(`[data-precio-manual="${i}"]`);
            const estado = document.querySelector(`[data-precio-estado="${i}"]`);
            const automatico = preciosAutomaticos.get(String(i));

            if (inputPrecio.readOnly) {
                const valor = parseFloat(inputPrecio.value || '0');
                if (!(valor > 0) || !(automatico > 0)) return;
                aplicarManual(i, valor, automatico);
                inputPrecio.focus();
                inputPrecio.select();
                return;
            }

            restaurarPrecioAutomatico(i);
        });
    });

    document.querySelectorAll('[data-precio]').forEach(inputPrecio => {
        inputPrecio.addEventListener('input', () => {
            if (inputPrecio.readOnly) return;

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

    turno.addEventListener('change', async () => {
        actualizarFecha();
        for (const check of document.querySelectorAll('[data-item-check]:checked')) {
            check.dataset.precioGuardado = '';
            await obtenerPrecio(check.dataset.itemCheck, check.dataset.productoId, false);
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
    (async () => {
        for (const check of document.querySelectorAll('[data-item-check]:checked')) {
            await activarFila(check, true, false);
        }
    })();
});
</script>
@endpush
