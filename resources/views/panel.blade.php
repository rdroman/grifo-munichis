@extends('layouts.aplicacion')

@section('titulo', 'Panel')
@section('encabezado', 'Panel de control')

@section('contenido')
    @php
        $fechaLegible = $fechaReferencia
            ? \Carbon\Carbon::parse($fechaReferencia)->translatedFormat('d \d\e F \d\e Y')
            : 'Sin datos';

        $maxVentaGrafico = max((float) ($ventasUltimos7Dias->max('ventas_brutas') ?? 0), 1);

        $rangoDia = $fechaReferencia
            ? ['desde' => $fechaReferencia, 'hasta' => $fechaReferencia]
            : [];

        $urlVentas = $fechaReferencia
            ? route('reportes.detalle', array_merge(['tipo' => 'ventas'], $rangoDia))
            : route('reportes.indice');

        $urlGalones = $fechaReferencia
            ? route('reportes.detalle', array_merge(['tipo' => 'galones'], $rangoDia))
            : route('reportes.indice');

        $urlCaja = $fechaReferencia
            ? route('reportes.detalle', array_merge(['tipo' => 'caja'], $rangoDia))
            : route('reportes.indice');
    @endphp

    <section class="encabezado-pagina gm-encabezado-directo">
        <div>
            <p class="eyebrow">Resumen operativo</p>
            <h1>Panel de control</h1>
        </div>

        <div class="estado-datos {{ $hayDatosHoy ? 'actual' : 'historico' }}">
            <span class="punto-estado"></span>
            {{ $fechaLegible }}
        </div>
    </section>

    <section class="rejilla-metricas gm-metricas-clicables" aria-label="Indicadores principales">
        <a class="tarjeta-metrica gm-card-link" href="{{ $urlVentas }}">
            <div class="metrica-icono verde">
                <x-icono nombre="dinero" tamano="22" />
            </div>
            <div>
                <span class="metrica-etiqueta">Ventas brutas</span>
                <strong class="metrica-valor">S/ {{ number_format($metricas['ventas_brutas'], 2) }}</strong>
            </div>
        </a>

        <a class="tarjeta-metrica gm-card-link gm-card-galones" href="{{ $urlGalones }}">
            <div class="metrica-icono amarillo">
                <x-icono nombre="combustible" tamano="22" />
            </div>
            <div>
                <span class="metrica-etiqueta">Galones despachados</span>
                <strong class="metrica-valor">{{ number_format($metricas['galones'], 3) }} gal</strong>

                @if($galonesPorProductoDia->isNotEmpty())
                    <div class="gm-mini-desglose">
                        @foreach($galonesPorProductoDia as $producto)
                            <span>
                                <b>{{ $producto->codigo }}</b>
                                {{ number_format((float) $producto->galones, 3) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </a>

        <a class="tarjeta-metrica gm-card-link" href="{{ $urlVentas }}">
            <div class="metrica-icono azul">
                <x-icono nombre="creditos" tamano="22" />
            </div>
            <div>
                <span class="metrica-etiqueta">Neto esperado</span>
                <strong class="metrica-valor">S/ {{ number_format($metricas['neto_esperado'], 2) }}</strong>
            </div>
        </a>

        <a class="tarjeta-metrica gm-card-link" href="{{ $urlCaja }}">
            <div class="metrica-icono {{ abs($metricas['diferencia']) <= 1 ? 'verde' : 'rojo' }}">
                <x-icono nombre="{{ abs($metricas['diferencia']) <= 1 ? 'check' : 'alerta' }}" tamano="22" />
            </div>
            <div>
                <span class="metrica-etiqueta">Diferencia de caja</span>
                <strong class="metrica-valor {{ $metricas['diferencia'] < 0 ? 'texto-rojo' : '' }}">
                    S/ {{ number_format($metricas['diferencia'], 2) }}
                </strong>
            </div>
        </a>
    </section>

    <section class="rejilla-dashboard">
        <article class="tarjeta dashboard-principal">
            <div class="tarjeta-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Rendimiento</span>
                    <h2>Ventas de los últimos 7 días</h2>
                </div>
                <a href="{{ route('modulos.mostrar', ['modulo' => 'reportes']) }}" class="enlace-secundario">
                    Ver reportes
                    <x-icono nombre="chevron" tamano="16" />
                </a>
            </div>

            @if ($ventasUltimos7Dias->isEmpty())
                <div class="estado-vacio compacto">
                    <x-icono nombre="reportes" tamano="30" />
                    <p>No hay ventas disponibles para graficar.</p>
                </div>
            @else
                <div class="grafico-barras" aria-label="Ventas de los últimos siete días">
                    @foreach ($ventasUltimos7Dias as $dia)
                        @php
                            $porcentaje = min(100, max(3, ((float) $dia->ventas_brutas / $maxVentaGrafico) * 100));
                        @endphp
                        <div class="barra-item" title="S/ {{ number_format((float) $dia->ventas_brutas, 2) }}">
                            <div class="barra-valor">S/ {{ number_format((float) $dia->ventas_brutas, 0) }}</div>
                            <div class="barra-pista">
                                <div class="barra-relleno" style="height: {{ $porcentaje }}%"></div>
                            </div>
                            <div class="barra-fecha">
                                {{ \Carbon\Carbon::parse($dia->fecha_operacion)->format('d/m') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="tarjeta">
            <div class="tarjeta-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Venta al público</span>
                    <h2>Precios vigentes</h2>
                </div>
                <a href="{{ route('modulos.mostrar', ['modulo' => 'precios']) }}" class="enlace-secundario">
                    Historial
                    <x-icono nombre="chevron" tamano="16" />
                </a>
            </div>

            <div class="lista-precios">
                @forelse ($preciosVigentes as $precio)
                    <div class="precio-item">
                        <div>
                            <span class="producto-codigo">{{ $precio->codigo_producto }}</span>
                            <strong>{{ $precio->nombre_producto }}</strong>
                            <small>
                                Desde {{ \Carbon\Carbon::parse($precio->vigente_desde)->format('d/m/Y H:i') }}
                            </small>
                        </div>
                        <div class="precio-valor">
                            <span>S/</span>
                            {{ number_format((float) $precio->precio, 2) }}
                        </div>
                    </div>
                @empty
                    <div class="estado-vacio compacto">
                        <p>No hay precios vigentes configurados.</p>
                    </div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="rejilla-dashboard secundaria">
        <article class="tarjeta tabla-tarjeta">
            <div class="tarjeta-cabecera">
                <div>
                    <span class="tarjeta-sobretitulo">Operación reciente</span>
                    <h2>Últimos turnos</h2>
                </div>
                <a href="{{ route('modulos.mostrar', ['modulo' => 'turnos']) }}" class="enlace-secundario">
                    Ver turnos
                    <x-icono nombre="chevron" tamano="16" />
                </a>
            </div>

            <div class="tabla-responsive">
                <table class="tabla-datos">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Turno</th>
                            <th>Trabajador</th>
                            <th class="numero">Venta</th>
                            <th class="numero">Gasto</th>
                            <th class="numero">Diferencia</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ultimosTurnos as $turno)
                            <tr>
                                <td data-etiqueta="Fecha">{{ \Carbon\Carbon::parse($turno->fecha_operacion)->format('d/m/Y') }}</td>
                                <td data-etiqueta="Turno"><span class="insignia turno">{{ $turno->codigo_turno }}</span></td>
                                <td data-etiqueta="Trabajador">{{ $turno->trabajador ?? '—' }}</td>
                                <td data-etiqueta="Venta" class="numero">S/ {{ number_format((float) $turno->ventas_brutas_combustible, 2) }}</td>
                                <td data-etiqueta="Gasto" class="numero">S/ {{ number_format((float) $turno->gastos_efectivo, 2) }}</td>
                                <td data-etiqueta="Diferencia" class="numero {{ (float) $turno->diferencia_caja < 0 ? 'texto-rojo' : '' }}">
                                    S/ {{ number_format((float) $turno->diferencia_caja, 2) }}
                                </td>
                                <td>
                                    <a class="boton-accion" href="{{ route('turnos.mostrar', $turno->turno_operativo_id) }}" title="Ver turno">
                                        <x-icono nombre="chevron" tamano="17"/>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="tabla-vacia">No existen turnos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="columna-resumen">
            <article class="tarjeta tarjeta-resumen">
                <div class="resumen-icono">
                    <x-icono nombre="turnos" />
                </div>
                <div>
                    <span>Último turno</span>
                    <strong>
                        @if ($ultimoTurno)
                            {{ $ultimoTurno->codigo_turno }} · {{ \Carbon\Carbon::parse($ultimoTurno->fecha_operacion)->format('d/m/Y') }}
                        @else
                            Sin datos
                        @endif
                    </strong>
                    <small>{{ $ultimoTurno->trabajador ?? '—' }}</small>
                </div>
            </article>

            <article class="tarjeta tarjeta-resumen">
                <div class="resumen-icono">
                    <x-icono nombre="trabajadores" />
                </div>
                <div>
                    <span>Trabajadores activos</span>
                    <strong>{{ $trabajadoresActivos }}</strong>
                    <small>Personal habilitado</small>
                </div>
            </article>

            <article class="tarjeta tarjeta-resumen {{ $validacionesPendientes > 0 ? 'con-alerta' : '' }}">
                <div class="resumen-icono">
                    <x-icono nombre="auditoria" />
                </div>
                <div>
                    <span>Validación digital</span>
                    <strong>{{ $validacionesPendientes }}</strong>
                    <small>{{ $validacionesPendientes === 1 ? 'turno pendiente' : 'turnos pendientes' }}</small>
                </div>
            </article>
        </aside>
    </section>
@endsection
