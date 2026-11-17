@extends('layouts.aplicacion')

@section('titulo',$esEdicion ? 'Editar pago mensual' : 'Nuevo pago mensual')
@section('encabezado','Pago mensual del personal')

@section('contenido')
<div class="encabezado-pagina">
    <div>
        <a href="{{ route('ajustes-personal.indice') }}" class="enlace-volver">
            <x-icono nombre="volver" tamano="17"/> Pago mensual del personal
        </a>

        <span class="tarjeta-sobretitulo">Personal</span>
        <h1>{{ $esEdicion ? 'Editar pago mensual' : 'Nuevo pago mensual' }}</h1>
        <p class="texto-secundario">
            Selecciona trabajador y mes. El sistema obtiene automáticamente adelantos, faltantes y sobrantes.
        </p>
    </div>
</div>

@if($errors->any())
    <div class="alerta alerta-peligro">{{ $errors->first() }}</div>
@endif

<form
    method="POST"
    action="{{ $esEdicion ? route('ajustes-personal.actualizar',$ajuste->id) : route('ajustes-personal.guardar') }}"
    id="form-pago-personal"
>
    @csrf
    @if($esEdicion) @method('PUT') @endif

    <section class="tarjeta pago-paso">
        <div class="pago-paso-numero">1</div>

        <div class="pago-paso-contenido">
            <div class="tarjeta-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Periodo</span>
                    <h2>¿A quién corresponde el pago?</h2>
                </div>
            </div>

            <div class="rejilla-campos">
                <div class="campo-formulario">
                    <label for="trabajador_id">Trabajador *</label>
                    <select name="trabajador_id" id="trabajador_id" required>
                        <option value="">Seleccione</option>
                        @foreach($trabajadores as $trabajador)
                            <option
                                value="{{ $trabajador->id }}"
                                @selected((string)old('trabajador_id',$valores['trabajador_id']) === (string)$trabajador->id)
                            >
                                {{ $trabajador->nombre_completo }}{{ $trabajador->activo ? '' : ' · Inactivo' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo-formulario">
                    <label for="mes">Mes *</label>
                    <input
                        type="month"
                        name="mes"
                        id="mes"
                        value="{{ old('mes',substr($valores['mes'],0,7)) }}"
                        required
                    >
                </div>
            </div>
        </div>
    </section>

    <section class="tarjeta pago-paso">
        <div class="pago-paso-numero">2</div>

        <div class="pago-paso-contenido">
            <div class="tarjeta-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Automático</span>
                    <h2>Movimientos del mes</h2>
                    <p class="texto-secundario">
                        Estos importes no se digitan. Se obtienen de Gastos y de los turnos validados por Administración.
                    </p>
                </div>
            </div>

            <div class="resumen-ajuste-grid">
                <article class="resumen-ajuste-card">
                    <span>(−) Adelantos</span>
                    <strong id="valor-adelantos">S/ {{ number_format($automatico['adelantos'],2) }}</strong>
                    <small>Adelantos acumulados del trabajador.</small>
                </article>

                <article class="resumen-ajuste-card resumen-faltante">
                    <span>(−) Faltantes</span>
                    <strong id="valor-faltantes">S/ {{ number_format($automatico['faltantes'],2) }}</strong>
                    <small>Faltantes confirmados en turnos validados.</small>
                </article>

                <article class="resumen-ajuste-card resumen-sobrante">
                    <span>(+) Sobrantes</span>
                    <strong id="valor-sobrantes">S/ {{ number_format($automatico['sobrantes'],2) }}</strong>
                    <small>Sobrantes confirmados en turnos validados.</small>
                </article>
            </div>

            <div
                id="aviso-pendientes"
                class="aviso-pendientes"
                style="{{ $automatico['turnos_pendientes'] > 0 ? '' : 'display:none;' }}"
            >
                <strong>Pendiente de conciliación:</strong>
                hay <span id="cantidad-pendientes">{{ $automatico['turnos_pendientes'] }}</span>
                turno(s) sin validación administrativa. Todavía no se incluyen en faltantes ni sobrantes.
            </div>
        </div>
    </section>

    <section class="tarjeta pago-paso">
        <div class="pago-paso-numero">3</div>

        <div class="pago-paso-contenido">
            <div class="tarjeta-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Pago</span>
                    <h2>Define el sueldo neto</h2>
                    <p class="texto-secundario">
                        Este es el único importe que debes ingresar manualmente.
                    </p>
                </div>
            </div>

            <div class="liquidacion-grid">
                <div class="campo-formulario">
                    <label for="sueldo_neto">Sueldo neto *</label>
                    <div class="entrada-moneda">
                        <span>S/</span>
                        <input
                            type="number"
                            name="sueldo_neto"
                            id="sueldo_neto"
                            min="0"
                            step="0.01"
                            value="{{ old('sueldo_neto',$valores['sueldo_neto']) }}"
                            placeholder="0.00"
                            required
                        >
                    </div>
                </div>

                <article class="saldo-sueldo-card">
                    <span>Saldo sueldo</span>
                    <strong id="valor-saldo-sueldo">S/ 0.00</strong>
                    <small>Este es el importe final que se paga al trabajador.</small>
                </article>
            </div>

            <div class="formula-sueldo">
                <span>Sueldo neto</span>
                <b>−</b>
                <span>Adelantos</span>
                <b>−</b>
                <span>Faltantes</span>
                <b>+</b>
                <span>Sobrantes</span>
                <b>=</b>
                <strong>Saldo sueldo</strong>
            </div>

            <div class="estado-preview">
                Estado:
                <strong id="estado-calculo">
                    {{ $estadoActual === 'LISTO_PARA_PAGAR' ? 'Listo para pagar' : 'Pendiente' }}
                </strong>
            </div>
        </div>
    </section>

    <div class="acciones-formulario">
        <a href="{{ route('ajustes-personal.indice') }}" class="boton boton-secundario">Cancelar</a>
        <button type="submit" class="boton boton-primario">
            {{ $esEdicion ? 'Guardar cambios' : 'Guardar pago mensual' }}
        </button>
    </div>
</form>

<style>
    .pago-paso {
        display:grid;
        grid-template-columns:42px minmax(0,1fr);
        gap:16px;
        margin-bottom:16px;
    }

    .pago-paso-numero {
        width:34px;
        height:34px;
        border-radius:50%;
        display:flex;
        align-items:center;
        justify-content:center;
        background:#f3f4f6;
        color:#344054;
        font-weight:900;
        margin-top:2px;
    }

    .pago-paso-contenido {
        min-width:0;
    }

    .resumen-ajuste-grid {
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:14px;
    }

    .resumen-ajuste-card {
        border:1px solid #e2e8f0;
        border-radius:14px;
        padding:18px;
        background:#f8fafc;
    }

    .resumen-ajuste-card span,
    .saldo-sueldo-card span {
        display:block;
        font-size:13px;
        font-weight:800;
        color:#475467;
    }

    .resumen-ajuste-card strong,
    .saldo-sueldo-card strong {
        display:block;
        margin-top:7px;
        font-size:26px;
        font-variant-numeric:tabular-nums;
    }

    .resumen-ajuste-card small,
    .saldo-sueldo-card small {
        display:block;
        margin-top:6px;
        color:#667085;
    }

    .resumen-faltante strong {
        color:#b42318;
    }

    .resumen-sobrante strong {
        color:#175cd3;
    }

    .aviso-pendientes {
        margin-top:14px;
        padding:12px 14px;
        border:1px solid #fedf89;
        background:#fffaeb;
        color:#93370d;
        border-radius:12px;
        font-size:13px;
    }

    .liquidacion-grid {
        display:grid;
        grid-template-columns:minmax(0,1fr) minmax(290px,.8fr);
        gap:18px;
        align-items:stretch;
    }

    .entrada-moneda {
        display:flex;
        align-items:center;
        border:1px solid #d0d5dd;
        border-radius:10px;
        overflow:hidden;
        background:#fff;
    }

    .entrada-moneda:focus-within {
        border-color:#15803d;
        box-shadow:0 0 0 3px rgba(21,128,61,.10);
    }

    .entrada-moneda > span {
        padding-left:14px;
        color:#667085;
        font-weight:800;
    }

    .entrada-moneda input {
        width:100%;
        border:0 !important;
        box-shadow:none !important;
    }

    .saldo-sueldo-card {
        border:1px solid #a6f4c5;
        background:#ecfdf3;
        border-radius:14px;
        padding:18px;
    }

    .saldo-sueldo-card strong {
        color:#067647;
        font-size:30px;
    }

    .formula-sueldo {
        display:flex;
        flex-wrap:wrap;
        align-items:center;
        gap:8px;
        margin-top:16px;
        padding:12px 14px;
        background:#f8fafc;
        border-radius:12px;
        color:#344054;
        font-size:13px;
    }

    .formula-sueldo b {
        color:#98a2b3;
    }

    .estado-preview {
        margin-top:12px;
        color:#667085;
        font-size:13px;
    }

    .estado-preview strong {
        color:#101828;
    }

    @media(max-width:800px) {
        .resumen-ajuste-grid,
        .liquidacion-grid {
            grid-template-columns:1fr;
        }
    }

    @media(max-width:600px) {
        .pago-paso {
            grid-template-columns:1fr;
        }

        .pago-paso-numero {
            margin-bottom:-5px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const trabajador = document.getElementById('trabajador_id');
    const mes = document.getElementById('mes');
    const sueldo = document.getElementById('sueldo_neto');

    const elAdelantos = document.getElementById('valor-adelantos');
    const elFaltantes = document.getElementById('valor-faltantes');
    const elSobrantes = document.getElementById('valor-sobrantes');
    const elSaldo = document.getElementById('valor-saldo-sueldo');
    const aviso = document.getElementById('aviso-pendientes');
    const cantidadPendientes = document.getElementById('cantidad-pendientes');
    const estadoCalculo = document.getElementById('estado-calculo');

    const resumenUrl = @json(route('ajustes-personal.resumen'));

    let automaticos = {
        adelantos: Number(@json((float)$automatico['adelantos'])),
        faltantes: Number(@json((float)$automatico['faltantes'])),
        sobrantes: Number(@json((float)$automatico['sobrantes'])),
        turnos_pendientes: Number(@json((int)$automatico['turnos_pendientes']))
    };

    const moneda = valor => 'S/ ' + Number(valor || 0).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    function pintar() {
        elAdelantos.textContent = moneda(automaticos.adelantos);
        elFaltantes.textContent = moneda(automaticos.faltantes);
        elSobrantes.textContent = moneda(automaticos.sobrantes);

        const sueldoNeto = Number(sueldo.value || 0);

        const saldo = sueldoNeto
            - Number(automaticos.adelantos || 0)
            - Number(automaticos.faltantes || 0)
            + Number(automaticos.sobrantes || 0);

        elSaldo.textContent = moneda(saldo);

        if (automaticos.turnos_pendientes > 0) {
            cantidadPendientes.textContent = automaticos.turnos_pendientes;
            aviso.style.display = '';
            estadoCalculo.textContent = 'Pendiente';
        } else {
            aviso.style.display = 'none';
            estadoCalculo.textContent = 'Listo para pagar';
        }
    }

    async function actualizarAutomaticos() {
        if (!trabajador.value || !mes.value) {
            automaticos = {
                adelantos: 0,
                faltantes: 0,
                sobrantes: 0,
                turnos_pendientes: 0
            };
            pintar();
            return;
        }

        const params = new URLSearchParams({
            trabajador_id: trabajador.value,
            mes: mes.value
        });

        try {
            const respuesta = await fetch(resumenUrl + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!respuesta.ok) {
                throw new Error('No se pudo obtener el resumen mensual.');
            }

            const datos = await respuesta.json();

            automaticos = {
                adelantos: Number(datos.adelantos || 0),
                faltantes: Number(datos.faltantes || 0),
                sobrantes: Number(datos.sobrantes || 0),
                turnos_pendientes: Number(datos.turnos_pendientes || 0)
            };

            pintar();
        } catch (error) {
            console.error(error);
        }
    }

    trabajador.addEventListener('change', actualizarAutomaticos);
    mes.addEventListener('change', actualizarAutomaticos);
    sueldo.addEventListener('input', pintar);

    pintar();
});
</script>
@endsection
