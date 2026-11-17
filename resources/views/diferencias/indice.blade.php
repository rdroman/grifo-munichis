@extends('layouts.aplicacion')

@section('titulo','Sobrantes y faltantes')
@section('encabezado','Sobrantes y faltantes')

@section('contenido')
<div class="encabezado-pagina diferencias-encabezado">
    <div>
        <span class="tarjeta-sobretitulo">Control financiero</span>
        <h1>Sobrantes y faltantes</h1>
        <p class="texto-secundario">
            La diferencia final se calcula únicamente con el dinero validado por Administración.
        </p>
    </div>

    <form method="GET" class="filtro-fechas-mini diferencias-filtro">
        <div class="campo-formulario">
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $desde }}">
        </div>

        <div class="campo-formulario">
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $hasta }}">
        </div>

        <button type="submit" class="boton boton-secundario boton-compacto">
            Aplicar
        </button>
    </form>
</div>

<section class="tarjeta">
    <div class="tarjeta-cabecera diferencias-cabecera-tabla">
        <div>
            <span class="tarjeta-sobretitulo">Detalle</span>
            <h2>Conciliación de turnos</h2>
            <p class="texto-secundario diferencias-formula">
                Diferencia = Dinero validado − Neto esperado
            </p>
        </div>
    </div>

    <div class="tabla-responsive">
        <table class="tabla-datos diferencias-tabla">
            <thead>
                <tr>
                    <th>Turno</th>
                    <th>Trabajador</th>
                    <th class="numero">Neto esperado</th>
                    <th class="numero">Dinero Operador</th>
                    <th class="numero">Dinero Validado</th>
                    <th class="numero">Diferencia</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse($registros as $r)
                    @php
                        $validado = ($r->estado ?? 'PENDIENTE') === 'VALIDADO';
                        $diferencia = $validado && $r->diferencia !== null
                            ? round((float)$r->diferencia, 2)
                            : null;

                        $claseDiferencia = '';
                        $textoDiferencia = '—';

                        if ($diferencia !== null) {
                            if ($diferencia < 0) {
                                $claseDiferencia = 'diferencia-faltante';
                            } elseif ($diferencia > 0) {
                                $claseDiferencia = 'diferencia-sobrante';
                            } else {
                                $claseDiferencia = 'diferencia-conforme';
                            }

                            $textoDiferencia = 'S/ '.number_format($diferencia, 2);
                        }

                        $efectivoOperador = (float)($r->efectivo_operador ?? 0);
                        $digitalOperador = (float)($r->digital_operador ?? 0);
                    @endphp

                    <tr>
                        <td>
                            <strong class="turno-codigo">{{ $r->turno ?? '—' }}</strong>
                            <small class="turno-fecha">
                                {{ $r->fecha_operacion
                                    ? \Carbon\Carbon::parse($r->fecha_operacion)->format('d/m/Y')
                                    : '—' }}
                            </small>
                        </td>

                        <td>
                            <strong>{{ $r->trabajador ?? '—' }}</strong>
                        </td>

                        <td class="numero">
                            <strong>S/ {{ number_format((float)$r->neto_esperado, 2) }}</strong>
                        </td>

                        <td class="numero">
                            <strong>S/ {{ number_format((float)$r->dinero_operador, 2) }}</strong>
                            <small class="desglose-dinero">
                                Ef. {{ number_format($efectivoOperador, 2) }}
                                + Dig. {{ number_format($digitalOperador, 2) }}
                            </small>
                        </td>

                        <td class="numero">
                            @if($validado)
                                <strong>S/ {{ number_format((float)$r->dinero_validado, 2) }}</strong>
                                <small class="desglose-dinero">
                                    Ef. {{ number_format((float)$r->efectivo_administrador, 2) }}
                                    + Dig. {{ number_format((float)$r->digital_administrador, 2) }}
                                </small>
                            @else
                                <span class="valor-pendiente">—</span>
                                <small class="desglose-dinero">Pendiente de validación completa</small>
                            @endif
                        </td>

                        <td class="numero">
                            <strong class="{{ $claseDiferencia }}">
                                {{ $textoDiferencia }}
                            </strong>
                        </td>

                        <td>
                            @if($validado)
                                <span class="estado-conciliacion estado-validado">VALIDADO</span>
                            @else
                                <span class="estado-conciliacion estado-pendiente">PENDIENTE</span>
                            @endif
                        </td>

                        <td class="numero">
                            <a
                                href="{{ route('turnos.mostrar', $r->turno_operativo_id) }}"
                                class="enlace-secundario"
                            >
                                Ver turno
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="estado-vacio compacto">
                                <p>No hay turnos en el periodo seleccionado.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($registros, 'links'))
        <div class="diferencias-paginacion">
            {{ $registros->links() }}
        </div>
    @endif
</section>

@push('estilos')
<style>
    .diferencias-encabezado {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
    }

    .diferencias-filtro {
        flex: 0 0 auto;
    }

    .diferencias-filtro .campo-formulario {
        min-width: 150px;
    }

    .diferencias-cabecera-tabla {
        margin-bottom: 6px;
    }

    .diferencias-formula {
        margin-top: 4px;
    }

    .diferencias-tabla td {
        vertical-align: middle;
    }

    .turno-codigo {
        display: block;
        color: #0f172a;
    }

    .turno-fecha,
    .desglose-dinero {
        display: block;
        margin-top: 3px;
        color: #64748b;
        font-size: .72rem;
        font-weight: 500;
        white-space: nowrap;
    }

    .valor-pendiente {
        color: #94a3b8;
        font-size: 1.1rem;
        font-weight: 800;
    }

    .diferencia-faltante {
        color: #b91c1c;
    }

    .diferencia-sobrante {
        color: #1d4ed8;
    }

    .diferencia-conforme {
        color: #111827;
    }

    .estado-conciliacion {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 5px 9px;
        font-size: .68rem;
        font-weight: 900;
        letter-spacing: .035em;
        white-space: nowrap;
    }

    .estado-validado {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }

    .estado-pendiente {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #fed7aa;
    }

    .diferencias-paginacion {
        margin-top: 18px;
    }

    @media (max-width: 900px) {
        .diferencias-encabezado {
            align-items: stretch;
            flex-direction: column;
        }

        .diferencias-filtro {
            width: 100%;
        }
    }
</style>
@endpush
@endsection
