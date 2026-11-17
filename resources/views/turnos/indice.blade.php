@extends('layouts.aplicacion')
@section('titulo','Turnos')
@section('encabezado','Turnos')
@section('contenido')

<div class="encabezado-pagina encabezado-pagina-acciones">
    <div>
        <span class="tarjeta-sobretitulo">Operación</span>
        <h1>{{ $esOperador ? 'Mis turnos' : 'Turnos operativos' }}</h1>
        <p class="texto-secundario">
            {{ $esOperador
                ? 'Solo se muestran los turnos vinculados a tu trabajador.'
                : 'Los turnos regulares siguen T1 → T2 → T3. Los cortes extraordinarios T4–T6 se registran sin reemplazar el turno regular pendiente.' }}
        </p>
    </div>

    <div style="display:flex;gap:.6rem;flex-wrap:wrap;justify-content:flex-end">
        @if(in_array(auth()->user()->rol,['ADMINISTRADOR','GERENCIA','SUPERVISOR','OPERADOR'],true))
            <a class="boton boton-primario" href="{{ route('turnos.crear') }}">
                <x-icono nombre="agregar" tamano="18"/>
                Registrar siguiente turno
            </a>
        @endif

        @if(auth()->user()->rol === 'ADMINISTRADOR')
            <a
                class="boton boton-secundario"
                href="{{ route('turnos.crear', ['modo' => 'extraordinario']) }}"
            >
                Registrar corte extraordinario
            </a>

            <button
                type="button"
                class="boton"
                id="abrir-reinicio-totalizadores"
                style="background:#b42318;color:#fff;border-color:#b42318"
                @disabled($reinicioPendiente)
            >
                Reinicio de contadores
            </button>
        @endif
    </div>
</div>

@if(session('exito'))
    <div class="alerta alerta-exito">
        <span>{{ session('exito') }}</span>
    </div>
@endif

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
            <strong>Reinicio de contadores pendiente.</strong>
            Se aplicará al siguiente turno o corte que se registre.
            Productos:
            <strong>
                {{ $productosReinicioPendiente->pluck('codigo')->implode(', ') ?: 'sin detalle' }}
            </strong>.
            Motivo: {{ $reinicioPendiente->motivo }}
        </span>
    </div>
@endif

@if($pendientes>0)
    <div class="alerta alerta-advertencia">
        <x-icono nombre="alerta"/>
        <span>
            {{ number_format($pendientes) }}
            turno{{ $pendientes===1?'':'s' }} con validación digital pendiente.
        </span>
    </div>
@endif

<section class="tarjeta filtros-tarjeta">
    <form method="GET" class="filtros-formulario filtros-extendidos">
        <div class="campo-formulario">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $desde }}">
        </div>

        <div class="campo-formulario">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}">
        </div>

        <div class="campo-formulario">
            <label>Turno</label>
            <select name="turno">
                <option value="">Todos</option>
                @foreach($tipos as $t)
                    <option
                        value="{{ $t->codigo }}"
                        @selected($codigo===$t->codigo)
                    >
                        {{ $t->codigo }} · {{ $t->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        @unless($esOperador)
            <div class="campo-formulario">
                <label>Trabajador</label>
                <select name="trabajador">
                    <option value="">Todos</option>
                    @foreach($trabajadores as $t)
                        <option
                            value="{{ $t->id }}"
                            @selected((string)$trabajador===(string)$t->id)
                        >
                            {{ $t->nombre_completo }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endunless

        <div class="filtros-acciones">
            <button class="boton boton-primario boton-compacto">
                Filtrar
            </button>
        </div>
    </form>
</section>

<section class="tarjeta tabla-tarjeta">
    <div class="tabla-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Turno</th>
                    <th>Motivo</th>
                    <th>Trabajador</th>
                    <th class="numero">Venta</th>
                    <th class="numero">Gasto</th>
                    <th class="numero">Neto</th>
                    <th class="numero">Diferencia</th>
                    <th>Validación</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse($turnos as $t)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($t->fecha_operacion)->format('d/m/Y') }}</td>
                        <td>
                            <span class="insignia estado-activo">
                                {{ $t->codigo_turno }}
                            </span>
                        </td>
                        <td>{{ $t->motivo ?? ($t->codigo_motivo ?? 'Normal') }}</td>
                        <td>{{ $t->trabajador ?? '—' }}</td>
                        <td class="numero">
                            S/ {{ number_format((float)($t->ventas_brutas_combustible??0),2) }}
                        </td>
                        <td class="numero">
                            S/ {{ number_format((float)($t->gastos_efectivo??0),2) }}
                        </td>
                        <td class="numero">
                            S/ {{ number_format((float)($t->neto_esperado??0),2) }}
                        </td>
                        <td class="numero {{ (float)($t->diferencia_caja??$t->diferencia_operador??0)<0?'texto-peligro':'' }}">
                            S/ {{ number_format((float)($t->diferencia_caja??$t->diferencia_operador??0),2) }}
                        </td>
                        <td>
                            <span class="insignia {{ ($t->estado_validacion_digital??'PENDIENTE')==='VALIDADO'?'estado-activo':(($t->estado_validacion_digital??'')==='OBSERVADO'?'estado-inactivo':'estado-pendiente') }}">
                                {{ $t->estado_validacion_digital??'PENDIENTE' }}
                            </span>
                        </td>
                        <td>
                            <a
                                class="boton-accion"
                                href="{{ route('turnos.mostrar',$t->id) }}"
                            >
                                <x-icono nombre="chevron" tamano="17"/>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="tabla-vacia">
                            No hay turnos.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@if(auth()->user()->rol === 'ADMINISTRADOR' && ! $reinicioPendiente)
    <style>
        .modal-reinicio-fondo {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10000;
            padding: 1rem;
            background: rgba(0, 0, 0, .48);
            align-items: center;
            justify-content: center;
        }

        .modal-reinicio-fondo.abierto {
            display: flex;
        }

        .modal-reinicio {
            width: min(640px, 100%);
            max-height: 90vh;
            overflow: auto;
            border-radius: 14px;
            background: #fff;
            padding: 1.25rem;
            box-shadow: 0 24px 70px rgba(0, 0, 0, .28);
        }

        .modal-reinicio-cabecera {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .modal-reinicio-productos {
            display: grid;
            gap: .65rem;
            margin: 1rem 0;
        }

        .modal-reinicio-producto {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .8rem;
            border: 1px solid #d8d8d8;
            border-radius: 10px;
            cursor: pointer;
        }

        .modal-reinicio-producto input {
            width: 18px;
            height: 18px;
        }

        .modal-reinicio-acciones {
            display: flex;
            justify-content: flex-end;
            gap: .6rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
    </style>

    <div
        id="modal-reinicio-totalizadores"
        class="modal-reinicio-fondo"
        aria-hidden="true"
    >
        <div
            class="modal-reinicio"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-reinicio-totalizadores"
        >
            <div class="modal-reinicio-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Administrador</span>
                    <h2 id="titulo-reinicio-totalizadores">
                        Reinicio de contadores
                    </h2>
                    <p class="texto-secundario">
                        Marca los productos cuyos totalizadores físicos fueron
                        reiniciados. Al seleccionar un producto se aplica a
                        todas sus mangueras/lados. Todos aparecen seleccionados
                        por defecto.
                    </p>
                </div>

                <button
                    type="button"
                    class="boton boton-secundario"
                    id="cerrar-reinicio-totalizadores"
                >
                    Cerrar
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('turnos.reinicio-totalizadores.guardar') }}"
                id="form-reinicio-totalizadores"
            >
                @csrf

                <input
                    type="hidden"
                    name="confirmacion"
                    value="1"
                >

                <div class="modal-reinicio-productos">
                    @forelse($productosReinicio as $producto)
                        <label class="modal-reinicio-producto">
                            <input
                                type="checkbox"
                                name="productos[]"
                                value="{{ $producto->id }}"
                                data-codigo="{{ $producto->codigo }}"
                                checked
                            >

                            <span>
                                <strong>{{ $producto->codigo }}</strong>
                                · {{ $producto->nombre }}
                                <small class="bloque texto-secundario">
                                    {{ $producto->cantidad_mangueras }}
                                    manguera{{ (int)$producto->cantidad_mangueras === 1 ? '' : 's' }}
                                </small>
                            </span>
                        </label>
                    @empty
                        <div class="alerta alerta-error">
                            <span>
                                No existen productos con mangueras activas para registrar un reinicio.
                            </span>
                        </div>
                    @endforelse
                </div>

                <div class="campo-formulario">
                    <label>Motivo del reinicio *</label>
                    <textarea
                        name="motivo"
                        rows="3"
                        maxlength="500"
                        required
                        placeholder="Ej.: Cambio de tarjeta electrónica del dispensador. Los totalizadores se reiniciaron a cero."
                    ></textarea>
                </div>

                <div class="modal-reinicio-acciones">
                    <button
                        type="button"
                        class="boton boton-secundario"
                        id="cancelar-reinicio-totalizadores"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="boton"
                        style="background:#b42318;color:#fff;border-color:#b42318"
                        @disabled($productosReinicio->isEmpty())
                    >
                        Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const abrir = document.getElementById('abrir-reinicio-totalizadores');
        const cerrar = document.getElementById('cerrar-reinicio-totalizadores');
        const cancelar = document.getElementById('cancelar-reinicio-totalizadores');
        const modal = document.getElementById('modal-reinicio-totalizadores');
        const formulario = document.getElementById('form-reinicio-totalizadores');

        function abrirModal() {
            modal?.classList.add('abierto');
            modal?.setAttribute('aria-hidden', 'false');
        }

        function cerrarModal() {
            modal?.classList.remove('abierto');
            modal?.setAttribute('aria-hidden', 'true');
        }

        abrir?.addEventListener('click', abrirModal);
        cerrar?.addEventListener('click', cerrarModal);
        cancelar?.addEventListener('click', cerrarModal);

        modal?.addEventListener('click', function (evento) {
            if (evento.target === modal) {
                cerrarModal();
            }
        });

        formulario?.addEventListener('submit', function (evento) {
            const seleccionados = Array.from(
                formulario.querySelectorAll(
                    'input[name="productos[]"]:checked'
                )
            );

            if (seleccionados.length === 0) {
                evento.preventDefault();
                alert('Debe seleccionar al menos un producto.');
                return;
            }

            const codigos = seleccionados
                .map(function (check) {
                    return check.dataset.codigo || check.value;
                })
                .join(', ');

            const aceptado = window.confirm(
                'Se registrará el reinicio de: ' + codigos + '.\n\n'
                + 'Todas las mangueras/lados de esos productos usarán '
                + '0.000 como nueva base en el siguiente turno o corte. '
                + 'Las lecturas anteriores permanecerán intactas.\n\n'
                + '¿Desea continuar?'
            );

            if (!aceptado) {
                evento.preventDefault();
            }
        });
    });
    </script>
@endif

@endsection
