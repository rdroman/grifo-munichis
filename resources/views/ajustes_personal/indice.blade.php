@extends('layouts.aplicacion')

@section('titulo','Pago mensual del personal')
@section('encabezado','Pago mensual del personal')

@section('contenido')
<div class="encabezado-pagina pago-personal-encabezado">
    <div>
        <span class="tarjeta-sobretitulo">Personal</span>
        <h1>Pago mensual del personal</h1>
        <p class="texto-secundario">
            Calcula el importe final que corresponde pagar a cada trabajador al cierre del mes.
        </p>
    </div>

    @if($puedeEditar)
        <a href="{{ route('ajustes-personal.crear') }}" class="boton boton-primario">
            <x-icono nombre="agregar" tamano="17"/> Nuevo pago mensual
        </a>
    @endif
</div>

@if(session('exito'))
    <div class="alerta alerta-exito">{{ session('exito') }}</div>
@endif

@if($errors->any())
    <div class="alerta alerta-peligro">{{ $errors->first() }}</div>
@endif

<section class="tarjeta pago-filtros">
    <form method="GET" action="{{ route('ajustes-personal.indice') }}" class="pago-filtros-grid">
        <div class="campo-formulario pago-buscar">
            <label>Trabajador</label>
            <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Buscar trabajador">
        </div>

        <div class="campo-formulario">
            <label>Año</label>
            <select name="anio">
                <option value="">Todos</option>
                @foreach($aniosDisponibles as $opcion)
                    <option value="{{ $opcion }}" @selected((string)$anio === (string)$opcion)>
                        {{ $opcion }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="campo-formulario">
            <label>Mes</label>
            <select name="mes">
                <option value="">Todos</option>
                @foreach([
                    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
                    5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
                    9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
                ] as $numero => $nombre)
                    <option value="{{ $numero }}" @selected((string)$mesNumero === (string)$numero)>
                        {{ $nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="campo-formulario">
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                <option value="PENDIENTE" @selected($estadoFiltro==='PENDIENTE')>Pendiente</option>
                <option value="LISTO_PARA_PAGAR" @selected($estadoFiltro==='LISTO_PARA_PAGAR')>Listo para pagar</option>
                <option value="PAGADO" @selected($estadoFiltro==='PAGADO')>Pagado</option>
            </select>
        </div>

        <div class="pago-filtros-acciones">
            <button type="submit" class="boton boton-primario">Filtrar</button>
            <a href="{{ route('ajustes-personal.indice') }}" class="boton boton-secundario">Limpiar</a>
        </div>
    </form>
</section>

<section class="tarjeta tabla-tarjeta">
    <div class="tarjeta-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Pagos mensuales</span>
            <h2>{{ $registros->total() }} registros</h2>
            <p class="texto-secundario">
                Saldo sueldo = Sueldo neto − Adelantos − Faltantes + Sobrantes.
            </p>
        </div>
    </div>

    <div class="tabla-responsive">
        <table class="tabla-datos pago-personal-tabla">
            <thead>
                <tr>
                    <th>Trabajador</th>
                    <th>Año</th>
                    <th>Mes</th>
                    <th class="numero">Sueldo neto</th>
                    <th class="numero">(-) Adelantos</th>
                    <th class="numero">(-) Faltantes</th>
                    <th class="numero">(+) Sobrantes</th>
                    <th class="numero">Saldo sueldo</th>
                    <th>Estado</th>
                    <th class="numero">Acciones</th>
                </tr>
            </thead>

            <tbody>
            @forelse($registros as $r)
                <tr>
                    <td><strong>{{ $r->trabajador }}</strong></td>
                    <td>{{ $r->anio_calculado }}</td>
                    <td class="texto-mes">{{ ucfirst($r->mes_nombre) }}</td>

                    <td class="numero">
                        S/ {{ number_format($r->sueldo_neto_calculado,2) }}
                    </td>

                    <td class="numero">
                        S/ {{ number_format($r->adelantos_calculados,2) }}
                    </td>

                    <td class="numero">
                        <span class="{{ $r->faltantes_calculados > 0 ? 'pago-faltante' : '' }}">
                            S/ {{ number_format($r->faltantes_calculados,2) }}
                        </span>
                    </td>

                    <td class="numero">
                        <span class="{{ $r->sobrantes_calculados > 0 ? 'pago-sobrante' : '' }}">
                            S/ {{ number_format($r->sobrantes_calculados,2) }}
                        </span>
                    </td>

                    <td class="numero">
                        <strong class="saldo-sueldo">
                            S/ {{ number_format($r->saldo_sueldo_calculado,2) }}
                        </strong>
                    </td>

                    <td>
                        @if($r->estado_calculado === 'PAGADO')
                            <span class="estado-pago estado-pagado">Pagado</span>
                            @if(!empty($r->pagado_en))
                                <small class="estado-detalle">
                                    {{ \Carbon\Carbon::parse($r->pagado_en)->format('d/m/Y H:i') }}
                                </small>
                            @endif
                        @elseif($r->estado_calculado === 'LISTO_PARA_PAGAR')
                            <span class="estado-pago estado-listo">Listo para pagar</span>
                        @else
                            <span class="estado-pago estado-pendiente">Pendiente</span>
                            <small class="estado-detalle">
                                {{ $r->turnos_pendientes }} turno{{ $r->turnos_pendientes == 1 ? '' : 's' }} sin validar
                            </small>
                        @endif
                    </td>

                    <td class="numero">
                        <div class="acciones-fila">
                            @if($puedeEditar && $r->estado_calculado !== 'PAGADO')
                                <a
                                    href="{{ route('ajustes-personal.editar',$r->id) }}"
                                    class="boton-accion"
                                    title="Editar pago mensual"
                                >
                                    <x-icono nombre="editar" tamano="17"/>
                                </a>
                            @endif

                            @if($puedeMarcarPago && $r->estado_calculado === 'LISTO_PARA_PAGAR')
                                <form method="POST" action="{{ route('ajustes-personal.pagado',$r->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="boton-accion boton-pagar"
                                        title="Marcar como pagado"
                                        onclick="return confirm('¿Confirmas que el saldo sueldo ya fue pagado al trabajador?')"
                                    >
                                        ✓
                                    </button>
                                </form>
                            @endif

                            @if($puedeMarcarPago && $r->estado_calculado === 'PAGADO')
                                <form method="POST" action="{{ route('ajustes-personal.reabrir',$r->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="boton-accion"
                                        title="Reabrir pago"
                                        onclick="return confirm('¿Deseas reabrir este pago mensual?')"
                                    >
                                        ↶
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="pago-vacio">
                        No hay pagos mensuales para mostrar.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($registros->hasPages())
        <div class="pago-paginacion">{{ $registros->links() }}</div>
    @endif
</section>

<style>
    .pago-personal-encabezado {
        display:flex;
        align-items:flex-end;
        justify-content:space-between;
        gap:18px;
    }

    .pago-filtros {
        margin-bottom:16px;
    }

    .pago-filtros-grid {
        display:grid;
        grid-template-columns:minmax(230px,1.5fr) repeat(3,minmax(135px,.7fr)) auto;
        gap:12px;
        align-items:end;
    }

    .pago-filtros-acciones {
        display:flex;
        gap:8px;
        align-items:center;
    }

    .pago-personal-tabla th,
    .pago-personal-tabla td {
        white-space:nowrap;
        vertical-align:middle;
    }

    .pago-personal-tabla td:first-child {
        min-width:170px;
    }

    .texto-mes {
        text-transform:capitalize;
    }

    .pago-faltante {
        color:#b42318;
        font-weight:800;
    }

    .pago-sobrante {
        color:#175cd3;
        font-weight:800;
    }

    .saldo-sueldo {
        color:#067647;
        font-size:1.02rem;
    }

    .estado-pago {
        display:inline-flex;
        align-items:center;
        min-height:27px;
        padding:4px 9px;
        border-radius:999px;
        font-size:11px;
        font-weight:800;
        white-space:nowrap;
    }

    .estado-pendiente {
        color:#b54708;
        background:#fffaeb;
        border:1px solid #fedf89;
    }

    .estado-listo {
        color:#175cd3;
        background:#eff8ff;
        border:1px solid #b2ddff;
    }

    .estado-pagado {
        color:#067647;
        background:#ecfdf3;
        border:1px solid #abefc6;
    }

    .estado-detalle {
        display:block;
        margin-top:4px;
        color:#667085;
        font-size:10px;
    }

    .acciones-fila {
        display:flex;
        justify-content:flex-end;
        gap:6px;
    }

    .acciones-fila form {
        margin:0;
    }

    .boton-pagar {
        color:#067647;
        font-weight:900;
    }

    .pago-vacio {
        text-align:center;
        padding:30px !important;
        color:#667085;
    }

    .pago-paginacion {
        padding:16px 20px;
    }

    @media(max-width:1000px) {
        .pago-filtros-grid {
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        .pago-buscar {
            grid-column:1 / -1;
        }
    }

    @media(max-width:650px) {
        .pago-personal-encabezado {
            align-items:stretch;
            flex-direction:column;
        }

        .pago-filtros-grid {
            grid-template-columns:1fr;
        }

        .pago-buscar {
            grid-column:auto;
        }

        .pago-filtros-acciones {
            flex-direction:column;
        }

        .pago-filtros-acciones .boton {
            width:100%;
            justify-content:center;
        }
    }
</style>
@endsection
