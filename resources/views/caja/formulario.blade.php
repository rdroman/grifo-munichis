@extends('layouts.aplicacion')

@section('titulo','Control de caja')
@section('encabezado','Control de caja')

@section('contenido')
@php
    $rol = auth()->user()->rol;
@endphp

<div class="encabezado-pagina caja-encabezado">
    <div>
        <a href="{{ route('caja.indice') }}" class="enlace-volver">
            <x-icono nombre="volver" tamano="17"/> Control de caja
        </a>
        <span class="tarjeta-sobretitulo">Arqueo diario</span>
        <h1>Control de caja</h1>
    </div>
</div>

@if($errors->any())
    <div class="alerta alerta-peligro caja-alerta">
        <strong>No se pudo guardar el control.</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('caja.guardar') }}" id="form-control-caja"
      data-resumen-url="{{ route('caja.resumen') }}">
    @csrf

    <section class="tarjeta caja-fecha-tarjeta">
        <div class="caja-fecha-contenido">
            <div>
                <span class="tarjeta-sobretitulo">Fecha del control</span>
                <h2>Selecciona el día a conciliar</h2>
            </div>

            <div class="campo-formulario caja-campo-fecha">
                <label for="fecha_control">Fecha *</label>
                <input
                    type="date"
                    id="fecha_control"
                    name="fecha_control"
                    value="{{ old('fecha_control',$fecha) }}"
                    required
                >
                <small id="estado-carga-fecha" class="caja-ayuda">Datos listos.</small>
            </div>
        </div>
    </section>

    <div class="caja-rejilla-principal">
        <section class="tarjeta caja-seccion">
            <div class="tarjeta-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Resumen del día</span>
                    <h2>Resumen de Caja</h2>
                </div>
            </div>

            <div class="caja-resumen-grid">
                <article class="caja-indicador">
                    <span class="caja-indicador-etiqueta">Efectivo esperado del día anterior</span>
                    <strong id="valor-efectivo-anterior" class="caja-indicador-valor">S/ 0.00</strong>
                </article>

                <article class="caja-indicador caja-indicador-destacado">
                    <span class="caja-indicador-etiqueta">Efectivo validado de turnos</span>
                    <strong id="valor-efectivo-dia" class="caja-indicador-valor">S/ 0.00</strong>
                    <div id="aviso-turnos-pendientes" class="caja-aviso-pendiente" hidden></div>
                </article>

                <article class="caja-indicador caja-indicador-informativo">
                    <div class="caja-etiqueta-linea">
                        <span class="caja-indicador-etiqueta">Efectivo digital del día</span>
                        <span class="caja-chip">Informativo</span>
                    </div>
                    <strong id="valor-digital-dia" class="caja-indicador-valor">S/ 0.00</strong>
                </article>
            </div>

            <div class="caja-otros-ingresos-bloque">
                <div class="campo-formulario">
                    <label for="otros_ingresos_caja">Otros Ingresos a Caja (S/)</label>
                    <div class="caja-input-moneda">
                        <span>S/</span>
                        <input
                            type="number"
                            min="0"
                            max="999999999999.99"
                            step="0.01"
                            id="otros_ingresos_caja"
                            name="otros_ingresos_caja"
                            value="{{ old('otros_ingresos_caja', number_format((float)($resumenInicial['otros_ingresos_caja'] ?? 0),2,'.','')) }}"
                            inputmode="decimal"
                        >
                    </div>
                </div>
            </div>
        </section>

        <section class="tarjeta caja-seccion">
            <div class="tarjeta-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Salidas de efectivo</span>
                    <h2>Movimientos que reducen la caja</h2>
                </div>
            </div>

            <div class="caja-salidas-lista">
                <div class="caja-fila-resumen">
                    <div>
                        <strong>Gastos en efectivo fuera de turno</strong>
                    </div>
                    <span id="valor-gastos-dia">S/ 0.00</span>
                </div>

                <div class="caja-fila-resumen">
                    <div>
                        <strong>Compras del día en efectivo</strong>
                    </div>
                    <span id="valor-compras-dia">S/ 0.00</span>
                </div>

                <div class="caja-deposito-bloque">
                    <div class="campo-formulario">
                        <label for="depositos_banco">Depósitos al banco</label>
                        <div class="caja-input-moneda">
                            <span>S/</span>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                id="depositos_banco"
                                name="depositos_banco"
                                value="{{ old('depositos_banco', number_format((float)($resumenInicial['depositos_banco'] ?? 0),2,'.','')) }}"
                                inputmode="decimal"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <section class="tarjeta caja-arqueo">
        <div class="tarjeta-cabecera">
            <div>
                <span class="tarjeta-sobretitulo">Arqueo de caja</span>
                <h2>Conciliación física</h2>
            </div>
        </div>

        <div class="caja-arqueo-grid">
            <article class="caja-esperado">
                <span>Efectivo esperado</span>
                <strong id="valor-efectivo-esperado">S/ 0.00</strong>
            </article>

            <div class="caja-conteo">
                <div class="campo-formulario">
                    <label for="efectivo_contado">Efectivo contado *</label>
                    <div class="caja-input-moneda caja-input-moneda-grande">
                        <span>S/</span>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            id="efectivo_contado"
                            name="efectivo_contado"
                            value="{{ old('efectivo_contado', $resumenInicial['efectivo_contado'] !== null ? number_format((float)$resumenInicial['efectivo_contado'],2,'.','') : '') }}"
                            placeholder="0.00"
                            inputmode="decimal"
                            required
                        >
                    </div>
                </div>
            </div>

            <article id="tarjeta-diferencia" class="caja-diferencia caja-diferencia-pendiente">
                <span>Diferencia</span>
                <strong id="valor-diferencia">—</strong>
                <em id="estado-diferencia">Pendiente de conteo</em>
            </article>
        </div>

        <div class="campo-formulario caja-observaciones">
            <label for="observaciones">Observaciones</label>
            <textarea
                id="observaciones"
                name="observaciones"
                rows="3"
                placeholder="Registra una explicación cuando exista un faltante, sobrante u otra novedad."
            >{{ old('observaciones', $resumenInicial['observaciones'] ?? '') }}</textarea>
        </div>
    </section>

    <div class="caja-acciones">
        <a href="{{ route('caja.indice') }}" class="boton boton-secundario">Cancelar</a>
        <button type="submit" class="boton boton-primario">
            <x-icono nombre="guardar" tamano="18"/> Guardar control de caja
        </button>
    </div>
</form>

@push('estilos')
<style>
    .caja-encabezado {
        margin-bottom: 18px;
    }

    .caja-alerta ul {
        margin: 8px 0 0 18px;
    }

    .caja-fecha-tarjeta {
        margin-bottom: 18px;
    }

    .caja-fecha-contenido {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
    }

    .caja-campo-fecha {
        width: min(280px, 100%);
        flex: 0 0 auto;
    }

    .caja-ayuda {
        display: block;
        margin-top: 6px;
        color: #64748b;
    }

    .caja-rejilla-principal {
        display: grid;
        grid-template-columns: minmax(0, 1.08fr) minmax(0, .92fr);
        gap: 18px;
        margin-bottom: 18px;
    }

    .caja-seccion {
        min-width: 0;
    }

    .caja-resumen-grid {
        display: grid;
        gap: 12px;
    }

    .caja-indicador {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px 18px;
        background: #f8fafc;
    }

    .caja-indicador-destacado {
        background: #fffdf2;
        border-color: #fde68a;
    }

    .caja-indicador-informativo {
        background: #f8fbff;
        border-color: #dbeafe;
    }

    .caja-indicador-etiqueta,
    .caja-esperado > span,
    .caja-diferencia > span {
        display: block;
        color: #475569;
        font-size: .82rem;
        font-weight: 700;
        letter-spacing: .015em;
    }

    .caja-indicador-valor {
        display: block;
        margin-top: 5px;
        color: #0f172a;
        font-size: 1.45rem;
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
    }

    .caja-aviso-pendiente {
        margin-top: 9px;
        border-radius: 9px;
        padding: 8px 10px;
        background: #fff7ed;
        color: #9a3412;
        font-size: .78rem;
        font-weight: 700;
    }

    .caja-indicador small,
    .caja-esperado small,
    .caja-diferencia small {
        display: block;
        margin-top: 5px;
        color: #64748b;
    }

    .caja-etiqueta-linea {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .caja-chip {
        border-radius: 999px;
        background: #eaf2ff;
        color: #1d4ed8;
        padding: 3px 8px;
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .035em;
    }

    .caja-salidas-lista {
        display: grid;
        gap: 12px;
    }

    .caja-fila-resumen {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        border-bottom: 1px solid #eef2f7;
        padding: 10px 0 14px;
    }

    .caja-fila-resumen strong {
        display: block;
        color: #0f172a;
    }

    .caja-fila-resumen small {
        display: block;
        margin-top: 3px;
        color: #64748b;
    }

    .caja-fila-resumen > span {
        color: #0f172a;
        font-size: 1.15rem;
        font-weight: 800;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .caja-otros-ingresos-bloque {
        margin-top: 12px;
        border: 1px solid #bbf7d0;
        border-radius: 14px;
        padding: 16px;
        background: #f0fdf4;
    }

    .caja-deposito-bloque {
        margin-top: 4px;
        border: 1px solid #facc15;
        border-radius: 14px;
        padding: 16px;
        background: #fffdf2;
    }

    .caja-input-moneda {
        display: flex;
        align-items: center;
        overflow: hidden;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #fff;
        transition: border-color .18s ease, box-shadow .18s ease;
    }

    .caja-input-moneda:focus-within {
        border-color: #ca8a04;
        box-shadow: 0 0 0 3px rgba(202,138,4,.12);
    }

    .caja-input-moneda > span {
        padding: 0 0 0 14px;
        color: #64748b;
        font-weight: 800;
    }

    .caja-input-moneda input {
        width: 100%;
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
        font-variant-numeric: tabular-nums;
    }

    .caja-arqueo {
        margin-bottom: 18px;
    }

    .caja-arqueo-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        align-items: stretch;
    }

    .caja-esperado,
    .caja-diferencia {
        min-height: 150px;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        border: 1px solid #e2e8f0;
    }

    .caja-esperado {
        background: #f8fafc;
    }

    .caja-esperado strong {
        margin-top: 8px;
        font-size: 1.9rem;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
    }

    .caja-conteo {
        min-height: 150px;
        display: flex;
        align-items: center;
        border: 2px solid #facc15;
        border-radius: 16px;
        padding: 20px;
        background: #fffdf2;
    }

    .caja-conteo .campo-formulario {
        width: 100%;
    }

    .caja-input-moneda-grande input {
        min-height: 52px;
        font-size: 1.25rem;
        font-weight: 800;
    }

    .caja-diferencia strong {
        margin-top: 8px;
        font-size: 1.9rem;
        font-variant-numeric: tabular-nums;
    }

    .caja-diferencia em {
        margin-top: 7px;
        font-style: normal;
        font-size: .78rem;
        font-weight: 900;
        letter-spacing: .045em;
        text-transform: uppercase;
    }

    .caja-diferencia-pendiente,
    .caja-diferencia-exacta {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #111827;
    }

    .caja-diferencia-faltante {
        background: #fef2f2;
        border-color: #fecaca;
        color: #b91c1c;
    }

    .caja-diferencia-sobrante {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }

    .caja-diferencia-faltante span,
    .caja-diferencia-faltante small,
    .caja-diferencia-sobrante span,
    .caja-diferencia-sobrante small {
        color: inherit;
        opacity: .9;
    }

    .caja-observaciones {
        margin-top: 18px;
    }

    .caja-observaciones textarea {
        width: 100%;
        resize: vertical;
    }

    .caja-acciones {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-bottom: 8px;
    }

    @media (max-width: 900px) {
        .caja-rejilla-principal,
        .caja-arqueo-grid {
            grid-template-columns: 1fr;
        }

        .caja-fecha-contenido {
            align-items: stretch;
            flex-direction: column;
        }

        .caja-campo-fecha {
            width: 100%;
        }

        .caja-esperado,
        .caja-diferencia,
        .caja-conteo {
            min-height: auto;
        }
    }

    @media (max-width: 560px) {
        .caja-fila-resumen {
            align-items: flex-start;
            flex-direction: column;
            gap: 6px;
        }

        .caja-acciones {
            flex-direction: column-reverse;
        }

        .caja-acciones .boton {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

<script>
(() => {
    const form = document.getElementById('form-control-caja');
    if (!form) return;

    const fecha = document.getElementById('fecha_control');
    const deposito = document.getElementById('depositos_banco');
    const otrosIngresosCaja = document.getElementById('otros_ingresos_caja');
    const contado = document.getElementById('efectivo_contado');
    const observaciones = document.getElementById('observaciones');
    const estadoCarga = document.getElementById('estado-carga-fecha');

    const elAnterior = document.getElementById('valor-efectivo-anterior');
    const elDia = document.getElementById('valor-efectivo-dia');
    const elDigital = document.getElementById('valor-digital-dia');
    const elGastos = document.getElementById('valor-gastos-dia');
    const elCompras = document.getElementById('valor-compras-dia');
    const elEsperado = document.getElementById('valor-efectivo-esperado');
    const elDiferencia = document.getElementById('valor-diferencia');
    const elEstadoDiferencia = document.getElementById('estado-diferencia');
    const tarjetaDiferencia = document.getElementById('tarjeta-diferencia');
    const avisoTurnosPendientes = document.getElementById('aviso-turnos-pendientes');

    const moneda = new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    let resumen = @json($resumenInicial);

    const numero = (valor) => {
        const n = Number(valor);
        return Number.isFinite(n) ? n : 0;
    };

    const pintarResumen = () => {
        const anterior = numero(resumen.efectivo_dia_anterior);
        const efectivoDia = numero(resumen.efectivo_total_dia);
        const digital = numero(resumen.efectivo_digital_dia);
        const gastos = numero(resumen.gastos_efectivo_dia);
        const compras = numero(resumen.compras_efectivo_dia);
        const depositos = numero(deposito.value);
        const otrosIngresos = numero(otrosIngresosCaja.value);

        const esperado = Math.round(
            (anterior + efectivoDia + otrosIngresos - gastos - compras - depositos) * 100
        ) / 100;

        elAnterior.textContent = moneda.format(anterior);
        elDia.textContent = moneda.format(efectivoDia);
        elDigital.textContent = moneda.format(digital);
        elGastos.textContent = moneda.format(gastos);
        elCompras.textContent = moneda.format(compras);
        elEsperado.textContent = moneda.format(esperado);

        const pendientes = numero(resumen.turnos_pendientes);
        if (avisoTurnosPendientes) {
            if (pendientes > 0) {
                avisoTurnosPendientes.hidden = false;
                avisoTurnosPendientes.textContent = pendientes === 1
                    ? 'Hay 1 turno sin efectivo validado; no está incluido en este importe.'
                    : `Hay ${pendientes} turnos sin efectivo validado; no están incluidos en este importe.`;
            } else {
                avisoTurnosPendientes.hidden = true;
                avisoTurnosPendientes.textContent = '';
            }
        }

        const tieneConteo = contado.value !== '' && Number.isFinite(Number(contado.value));

        tarjetaDiferencia.classList.remove(
            'caja-diferencia-pendiente',
            'caja-diferencia-exacta',
            'caja-diferencia-faltante',
            'caja-diferencia-sobrante'
        );

        if (!tieneConteo) {
            elDiferencia.textContent = '—';
            elEstadoDiferencia.textContent = 'Pendiente de conteo';
            tarjetaDiferencia.classList.add('caja-diferencia-pendiente');
            return;
        }

        const diferencia = Math.round((numero(contado.value) - esperado) * 100) / 100;
        elDiferencia.textContent = moneda.format(diferencia);

        if (Math.abs(diferencia) < 0.005) {
            elEstadoDiferencia.textContent = 'Caja conforme';
            tarjetaDiferencia.classList.add('caja-diferencia-exacta');
        } else if (diferencia < 0) {
            elEstadoDiferencia.textContent = 'Faltante';
            tarjetaDiferencia.classList.add('caja-diferencia-faltante');
        } else {
            elEstadoDiferencia.textContent = 'Sobrante';
            tarjetaDiferencia.classList.add('caja-diferencia-sobrante');
        }
    };

    const cargarFecha = async () => {
        if (!fecha.value) return;

        estadoCarga.textContent = 'Actualizando datos…';

        const url = new URL(form.dataset.resumenUrl, window.location.origin);
        url.searchParams.set('fecha', fecha.value);

        try {
            const respuesta = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!respuesta.ok) {
                throw new Error('No fue posible obtener el resumen diario.');
            }

            resumen = await respuesta.json();

            deposito.value = numero(resumen.depositos_banco).toFixed(2);
            otrosIngresosCaja.value = numero(resumen.otros_ingresos_caja).toFixed(2);
            contado.value = resumen.efectivo_contado === null || resumen.efectivo_contado === undefined
                ? ''
                : numero(resumen.efectivo_contado).toFixed(2);
            observaciones.value = resumen.observaciones || '';

            estadoCarga.textContent = resumen.existe
                ? 'Este día ya tiene un control. Puedes actualizarlo.'
                : 'Datos calculados para un nuevo control.';

            pintarResumen();
        } catch (error) {
            estadoCarga.textContent = 'No se pudieron actualizar los valores. Revisa la conexión e inténtalo nuevamente.';
        }
    };

    deposito.addEventListener('input', pintarResumen);
    otrosIngresosCaja.addEventListener('input', pintarResumen);
    contado.addEventListener('input', pintarResumen);
    fecha.addEventListener('change', cargarFecha);

    pintarResumen();
})();
</script>
@endsection
