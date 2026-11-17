@extends('layouts.aplicacion')
@section('titulo','Detalle del turno')
@section('encabezado','Turnos')
@section('contenido')

@php
    $rol = auth()->user()->rol;

    $montoEntregar = (float) (
        $calculoFinanciero['monto_a_entregar']
        ?? data_get($financiero, 'neto_esperado', 0)
    );

    /*
    |--------------------------------------------------------------------------
    | Conciliacion de entrega
    |--------------------------------------------------------------------------
    | Para datos historicos, si el operador no informo un componente (NULL o 0),
    | se usa el valor validado por administracion del mismo componente.
    |--------------------------------------------------------------------------
    */
    $efectivoOperadorOriginal = data_get($financiero, 'efectivo_entregado');
    $digitalOperadorOriginal = data_get(
        $financiero,
        'digital_operador',
        data_get($financiero, 'digital_entregado')
    );

    $efectivoAdmin = data_get($financiero, 'efectivo_administrador');
    $digitalAdmin = data_get($financiero, 'digital_administrador');

    $efectivoOperador = (
        ($efectivoOperadorOriginal === null || (float)$efectivoOperadorOriginal == 0.0)
        && $efectivoAdmin !== null
    )
        ? (float)$efectivoAdmin
        : (float)($efectivoOperadorOriginal ?? 0);

    $digitalOperador = (
        ($digitalOperadorOriginal === null || (float)$digitalOperadorOriginal == 0.0)
        && $digitalAdmin !== null
    )
        ? (float)$digitalAdmin
        : (float)($digitalOperadorOriginal ?? 0);

    $montoEntregadoOperador = round(
        $efectivoOperador + $digitalOperador,
        2
    );

    // Para mostrar el total administrativo se usa cada valor validado y,
    // si un componente aun no existe, el valor efectivo del operador.
    $hayValidacionAdministrativa = $efectivoAdmin !== null || $digitalAdmin !== null;

    $montoValidadoAdministrador = $hayValidacionAdministrativa
        ? round(
            (float)($efectivoAdmin ?? $efectivoOperador)
            + (float)($digitalAdmin ?? $digitalOperador),
            2
        )
        : null;

    // El turno se considera validado para la diferencia principal solo cuando
    // efectivo y dinero digital completaron su validacion administrativa.
    $turnoValidado =
        data_get($financiero, 'validado_efectivo_en') !== null
        && data_get($financiero, 'estado_validacion_digital') === 'VALIDADO';

    $montoBaseDiferencia = (
        $turnoValidado && $montoValidadoAdministrador !== null
    )
        ? $montoValidadoAdministrador
        : $montoEntregadoOperador;

    $diferenciaResumen = round(
        $montoBaseDiferencia - $montoEntregar,
        2
    );

    // Bloqueo visible (en servidor se verifica de nuevo en cada POST/PUT/PATCH).
    $turnoBloqueadoSupervisor = $rol === 'SUPERVISOR'
        && \App\Support\PermisosSupervisor::turnoCerradoParaSupervisor((int)$cabecera->id);

    // Si es OPERADOR, ControladorTurnos::mostrar() ya restringe el acceso a su propio turno.
    $puedeEditarTurno = $rol === 'ADMINISTRADOR' || ($rol === 'SUPERVISOR' && ! $turnoBloqueadoSupervisor) || (
        $esOperador &&
        (string)$cabecera->fecha_operacion === now('America/Lima')->toDateString() &&
        (int)data_get($cabecera,'ediciones_operador',0) < 1
    );
@endphp

<div class="encabezado-pagina">
    <div>
        <a href="{{ route('turnos.indice') }}" class="enlace-volver"><x-icono nombre="volver" tamano="17"/> Turnos</a>
        <h1>{{ $cabecera->codigo_turno }} · {{ \Carbon\Carbon::parse($cabecera->fecha_operacion)->format('d/m/Y') }}</h1>
        <p class="texto-secundario">{{ $cabecera->trabajador ?: 'Sin trabajador' }}@if(data_get($cabecera,'motivo')) · {{ $cabecera->motivo }}@endif</p>
    </div>
    @if($puedeEditarTurno)
        <a class="boton boton-secundario" href="{{ route('turnos.editar',$cabecera->id) }}"><x-icono nombre="editar" tamano="17"/> Modificar turno</a>
    @endif
</div>

@if($turnoBloqueadoSupervisor)<div class="alerta alerta-advertencia">Turno validado por administración: el Supervisor solo puede consultarlo. No se permiten modificaciones ni nuevas operaciones asociadas a este turno.</div>@endif
@if(session('exito'))<div class="alerta alerta-exito">{{ session('exito') }}</div>@endif
@if($errors->any())<div class="alerta alerta-error">{{ $errors->first() }}</div>@endif

@if(!empty($calculoFinanciero['inconsistencias']))
<div class="alerta alerta-advertencia">
    <div><strong>Revisar consistencia del turno:</strong>
        @foreach($calculoFinanciero['inconsistencias'] as $mensaje)<div>{{ $mensaje }}</div>@endforeach
    </div>
</div>
@endif

<style>
    /* Resumen financiero compacto y jerarquizado */
    .conciliacion-resumen-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 12px;
    }

    .conciliacion-resumen-card {
        position: relative;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 15px 16px 14px;
        min-height: 96px;
        background: #fff;
        box-shadow: 0 2px 9px rgba(15, 23, 42, .045);
    }

    .conciliacion-resumen-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        right: 0;
        height: 3px;
        background: #cbd5e1;
    }

    .conciliacion-resumen-card.es-entregar {
        background: linear-gradient(180deg, #f7fff9 0%, #ffffff 100%);
    }
    .conciliacion-resumen-card.es-entregar::before { background: #15803d; }

    .conciliacion-resumen-card.es-operador::before { background: #64748b; }

    .conciliacion-resumen-card.es-administrador {
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    }
    .conciliacion-resumen-card.es-administrador::before { background: #2563eb; }

    .conciliacion-resumen-card.es-diferencia.diferencia-card-negativa {
        background: linear-gradient(180deg, #fff8f8 0%, #ffffff 100%);
        border-color: #fecaca;
    }
    .conciliacion-resumen-card.es-diferencia.diferencia-card-negativa::before { background: #dc2626; }

    .conciliacion-resumen-card.es-diferencia.diferencia-card-no-negativa {
        background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
        border-color: #bfdbfe;
    }
    .conciliacion-resumen-card.es-diferencia.diferencia-card-no-negativa::before { background: #2563eb; }

    .conciliacion-resumen-etiqueta {
        display: block;
        color: #64748b;
        font-size: .69rem;
        font-weight: 800;
        letter-spacing: .045em;
        line-height: 1.28;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .conciliacion-resumen-valor {
        display: block;
        color: #0f172a;
        font-size: 1.50rem;
        line-height: 1.08;
        font-weight: 800;
        letter-spacing: -.025em;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .conciliacion-resumen-valor.diferencia-negativa { color: #dc2626; }
    .conciliacion-resumen-valor.diferencia-no-negativa { color: #2563eb; }

    .conciliacion-resumen-ayuda {
        display: block;
        color: #64748b;
        font-size: .70rem;
        line-height: 1.3;
        margin-top: 6px;
    }

    /* Resumen por producto */
    .tabla-resumen-producto {
        width: 100%;
        table-layout: fixed;
    }

    .tabla-resumen-producto th,
    .tabla-resumen-producto td {
        vertical-align: middle;
    }

    .tabla-resumen-producto .col-producto { width: 18%; }
    .tabla-resumen-producto .col-galones { width: 22%; }
    .tabla-resumen-producto .col-precio { width: 12%; }
    .tabla-resumen-producto .col-importe { width: 16%; }

    .producto-codigo {
        display: block;
        font-size: .86rem;
        font-weight: 800;
        color: #0f172a;
    }

    .producto-nombre {
        display: block;
        margin-top: 2px;
        font-size: .72rem;
        line-height: 1.25;
        color: #64748b;
    }

    .galones-desglose {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 3px 10px;
        min-width: 150px;
    }

    .galones-desglose span {
        font-size: .70rem;
        color: #64748b;
        white-space: nowrap;
    }

    .galones-desglose strong {
        font-size: .76rem;
        color: #334155;
        font-variant-numeric: tabular-nums;
        text-align: right;
    }

    .galones-desglose .vendido-label,
    .galones-desglose .vendido-valor {
        color: #0f172a;
        font-weight: 800;
        padding-top: 3px;
        border-top: 1px solid #e2e8f0;
    }

    .importe-principal {
        font-weight: 800;
        color: #0f172a;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .importe-secundario {
        display: block;
        margin-top: 3px;
        color: #64748b;
        font-size: .70rem;
        white-space: nowrap;
    }

    .producto-total-barra {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-top: 12px;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
    }

    .producto-total-item {
        padding: 2px 8px;
    }

    .producto-total-item span {
        display: block;
        color: #64748b;
        font-size: .67rem;
        font-weight: 800;
        letter-spacing: .035em;
        text-transform: uppercase;
    }

    .producto-total-item strong {
        display: block;
        margin-top: 4px;
        color: #0f172a;
        font-size: 1rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .producto-total-item.es-neto {
        border-left: 3px solid #15803d;
        padding-left: 12px;
        background: #f7fff9;
        border-radius: 8px;
    }

    .producto-total-item.es-neto strong {
        color: #166534;
        font-size: 1.12rem;
    }

    @media (max-width: 1050px) {
        .conciliacion-resumen-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .producto-total-barra { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 760px) {
        .tabla-resumen-producto { min-width: 820px; table-layout: auto; }
    }

    @media (max-width: 620px) {
        .conciliacion-resumen-grid { grid-template-columns: 1fr; }
        .producto-total-barra { grid-template-columns: 1fr 1fr; }
    }
</style>

<section class="tarjeta" id="resumen-conciliacion-turno" data-turno-validado="{{ $turnoValidado ? '1' : '0' }}" data-monto-entregar="{{ number_format($montoEntregar,2,'.','') }}">
    <div class="tarjeta-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Resumen financiero</span>
            <h2>Conciliación del turno</h2>
        </div>
    </div>

    <div class="conciliacion-resumen-grid">
        <article class="conciliacion-resumen-card es-entregar">
            <span class="conciliacion-resumen-etiqueta">Monto a entregar</span>
            <strong class="conciliacion-resumen-valor" id="resumen-monto-entregar">S/ {{ number_format($montoEntregar,2) }}</strong>
            <small class="conciliacion-resumen-ayuda">Importe esperado después de gastos.</small>
        </article>

        <article class="conciliacion-resumen-card es-operador">
            <span class="conciliacion-resumen-etiqueta">Entregado por operador</span>
            <strong class="conciliacion-resumen-valor" id="resumen-monto-operador">S/ {{ number_format($montoEntregadoOperador,2) }}</strong>
            <small class="conciliacion-resumen-ayuda">Efectivo + dinero digital informado.</small>
        </article>

        <article class="conciliacion-resumen-card es-administrador">
            <span class="conciliacion-resumen-etiqueta">Validado por administración</span>
            <strong class="conciliacion-resumen-valor" id="resumen-monto-administrador">{{ $montoValidadoAdministrador === null ? 'Pendiente' : 'S/ '.number_format($montoValidadoAdministrador,2) }}</strong>
            <small class="conciliacion-resumen-ayuda">Efectivo + digital validados.</small>
        </article>

        <article class="conciliacion-resumen-card es-diferencia {{ $diferenciaResumen < 0 ? 'diferencia-card-negativa' : 'diferencia-card-no-negativa' }}" id="resumen-card-diferencia">
            <span class="conciliacion-resumen-etiqueta">Diferencia</span>
            <strong class="conciliacion-resumen-valor {{ $diferenciaResumen < 0 ? 'diferencia-negativa' : 'diferencia-no-negativa' }}" id="resumen-diferencia">S/ {{ number_format($diferenciaResumen,2) }}</strong>
            <small class="conciliacion-resumen-ayuda" id="resumen-diferencia-base">
                {{ $turnoValidado ? 'Sobre el monto validado por administración.' : 'Sobre el monto entregado por el operador.' }}
            </small>
        </article>
    </div>
</section>

<section class="tarjeta" id="resumen-por-producto">
    <div class="tarjeta-cabecera">
        <div>
            <span class="tarjeta-sobretitulo">Resumen</span>
            <h2>Por producto</h2>
            <p class="texto-secundario">Venta normal = despacho físico − créditos − descuentos. El monto neto vendido suma la venta normal y los descuentos cobrados.</p>
        </div>
        @if(! $turnoBloqueadoSupervisor && ($esOperador || in_array($rol,['ADMINISTRADOR','GERENCIA','SUPERVISOR'],true)))
            <a class="boton boton-secundario boton-compacto" href="{{ route('descuentos.crear',['turno_operativo_id'=>$cabecera->id]) }}"><x-icono nombre="agregar" tamano="16"/> Registrar descuento</a>
        @endif
    </div>

    <div class="tabla-responsive">
        <table class="tabla-datos tabla-resumen-producto">
            <thead>
                <tr>
                    <th class="col-producto">Producto</th>
                    <th class="col-galones">Galones</th>
                    <th class="numero col-precio">Precio vigente</th>
                    <th class="numero col-importe">Venta normal</th>
                    <th class="numero col-importe">Descuentos</th>
                    <th class="numero col-importe">Créditos</th>
                    <th class="numero col-importe">Neto vendido</th>
                </tr>
            </thead>
            <tbody>
            @forelse($calculoFinanciero['productos'] as $p)
                <tr>
                    <td>
                        <span class="producto-codigo">{{ $p['codigo'] }}</span>
                        <span class="producto-nombre">{{ $p['nombre'] }}</span>
                    </td>
                    <td>
                        <div class="galones-desglose">
                            <span>Despachado</span><strong>{{ number_format((float)$p['galones_despachados'],3) }}</strong>
                            <span>Crédito</span><strong>{{ number_format((float)$p['galones_credito'],3) }}</strong>
                            <span>Descuento</span><strong>{{ number_format((float)$p['galones_descuento'],3) }}</strong>
                            <span class="vendido-label">Vendido</span><strong class="vendido-valor">{{ number_format((float)$p['galones_vendidos'],3) }}</strong>
                        </div>
                    </td>
                    <td class="numero">
                        <span class="importe-principal">{{ $p['precio_vigente']===null?'—':'S/ '.number_format((float)$p['precio_vigente'],4) }}</span>
                    </td>
                    <td class="numero">
                        <span class="importe-principal">S/ {{ number_format((float)$p['importe_venta_normal'],2) }}</span>
                        <span class="importe-secundario">{{ number_format((float)$p['galones_vendidos'],3) }} gal</span>
                    </td>
                    <td class="numero">
                        <span class="importe-principal">S/ {{ number_format((float)$p['importe_descuentos'],2) }}</span>
                        <span class="importe-secundario">{{ number_format((float)$p['galones_descuento'],3) }} gal</span>
                    </td>
                    <td class="numero">
                        <span class="importe-principal">S/ {{ number_format((float)$p['importe_creditos'],2) }}</span>
                        <span class="importe-secundario">{{ number_format((float)$p['galones_credito'],3) }} gal</span>
                    </td>
                    <td class="numero">
                        <span class="importe-principal">S/ {{ number_format((float)$p['importe_neto'],2) }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="tabla-vacia">No hay despacho registrado en este turno.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="producto-total-barra">
        <div class="producto-total-item">
            <span>Venta normal total</span>
            <strong>S/ {{ number_format((float)($calculoFinanciero['venta_normal_combustible'] ?? 0),2) }}</strong>
        </div>
        <div class="producto-total-item">
            <span>Descuentos cobrados</span>
            <strong>S/ {{ number_format((float)($calculoFinanciero['importe_descuentos'] ?? 0),2) }}</strong>
        </div>
        <div class="producto-total-item">
            <span>Créditos (informativo)</span>
            <strong>S/ {{ number_format((float)($calculoFinanciero['importe_creditos'] ?? 0),2) }}</strong>
        </div>
        <div class="producto-total-item es-neto">
            <span>Monto neto vendido total</span>
            <strong>S/ {{ number_format((float)($calculoFinanciero['monto_neto_combustible'] ?? 0),2) }}</strong>
        </div>
    </div>
</section>

<section class="tarjeta">
    <div class="tarjeta-cabecera"><div><span class="tarjeta-sobretitulo">Despacho</span><h2>Totalizadores</h2></div></div>
    <div class="tabla-responsive"><table class="tabla-datos"><thead><tr><th>Producto</th><th>Manguera</th><th class="numero">Lectura final</th><th class="numero">Galones despachados</th></tr></thead><tbody>
    @foreach($lecturas as $l)<tr><td>{{ $l->producto_codigo }} · {{ $l->producto }}</td><td>{{ $l->manguera }}</td><td class="numero">{{ number_format((float)($l->lectura??$l->totalizador??0),3) }}</td><td class="numero">{{ number_format((float)($l->galones_vendidos??$l->galones??0),3) }}</td></tr>@endforeach
    </tbody></table></div>
</section>

<div class="rejilla-dos-columnas">
<section class="tarjeta tabla-tarjeta">
    <div class="tarjeta-cabecera"><div><span class="tarjeta-sobretitulo">Egresos</span><h2>Gastos</h2></div>
        @if(! $turnoBloqueadoSupervisor && ($esOperador || in_array($rol,['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD'],true)))
        <a class="boton boton-secundario boton-compacto" href="{{ route('crud.crear',['modulo'=>'gastos','turno_operativo_id'=>$cabecera->id]) }}"><x-icono nombre="agregar" tamano="16"/> Registrar gasto</a>@endif
    </div>
    <div class="tabla-responsive"><table class="tabla-datos"><thead><tr><th>Fecha</th><th>Detalle</th><th class="numero">Monto</th></tr></thead><tbody>
    @forelse($gastos as $g)
        @php $fg=$columnasGasto['fecha']?data_get($g,$columnasGasto['fecha']):null; $dg=$columnasGasto['detalle']?data_get($g,$columnasGasto['detalle']):null; $mg=$columnasGasto['monto']?data_get($g,$columnasGasto['monto']):0; @endphp
        <tr><td>{{ $fg?\Carbon\Carbon::parse($fg)->format('d/m/Y'):'—' }}</td><td>{{ $dg ?: '—' }}</td><td class="numero">S/ {{ number_format((float)$mg,2) }}</td></tr>
    @empty<tr><td colspan="3" class="tabla-vacia">No hay gastos registrados.</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="tarjeta tabla-tarjeta">
    <div class="tarjeta-cabecera"><div><span class="tarjeta-sobretitulo">Ventas</span><h2>Créditos</h2></div>
        @if(! $turnoBloqueadoSupervisor && ($esOperador || in_array($rol,['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD'],true)))
        <a class="boton boton-secundario boton-compacto" href="{{ route('creditos.crear',['turno_operativo_id'=>$cabecera->id]) }}"><x-icono nombre="agregar" tamano="16"/> Registrar crédito</a>@endif
    </div>
    <div class="tabla-responsive"><table class="tabla-datos"><thead><tr><th>Cliente</th><th>Vale</th><th class="numero">Total</th><th></th></tr></thead><tbody>
    @forelse($creditos as $c)
        @php $vale=data_get($c,'numero_vale') ?? data_get($c,'numero_comprobante') ?? data_get($c,'vale_numero'); @endphp
        <tr><td>{{ $c->cliente ?? '—' }}</td><td>{{ $vale ?: '—' }}</td><td class="numero">S/ {{ number_format((float)($totalesCreditos[$c->id]??0),2) }}</td><td><a class="boton-accion" href="{{ route('creditos.mostrar',$c->id) }}"><x-icono nombre="chevron" tamano="17"/></a></td></tr>
    @empty<tr><td colspan="4" class="tabla-vacia">No hay ventas a crédito.</td></tr>@endforelse
    </tbody></table></div>
</section>
</div>

@if(($esOperador || $rol === 'SUPERVISOR') && ! $turnoBloqueadoSupervisor)
<section class="tarjeta" id="entrega-turno">
    <div class="tarjeta-cabecera"><div><span class="tarjeta-sobretitulo">Cierre</span><h2>Entrega del operador</h2><p class="texto-secundario">Diferencia = (efectivo + dinero digital) − monto a entregar.</p></div></div>
    <form method="POST" action="{{ route('turnos.entrega',$cabecera->id) }}" class="formulario-inline formulario-wrap" id="form-entrega" data-esperado="{{ number_format($montoEntregar,2,'.','') }}">
        @csrf @method('PATCH')
        <div class="campo-formulario"><label>Monto a entregar</label><input value="{{ number_format($montoEntregar,2,'.','') }}" readonly></div>
        <div class="campo-formulario"><label>Efectivo *</label><input id="entrega-efectivo" type="number" min="0" step="0.01" name="efectivo_entregado" value="{{ old('efectivo_entregado',number_format($efectivoOperador,2,'.','')) }}" required></div>
        <div class="campo-formulario"><label>Dinero digital *</label><input id="entrega-digital" type="number" min="0" step="0.01" name="digital_operador" value="{{ old('digital_operador',number_format($digitalOperador,2,'.','')) }}" required></div>
        <div class="campo-formulario"><label>Total entregado</label><input id="entrega-total" readonly></div>
        <div class="campo-formulario"><label>Diferencia</label><input id="entrega-diferencia" readonly></div>
        <div class="campo-formulario"><label>Estado</label><input id="entrega-estado" readonly></div>
        <button class="boton boton-primario"><x-icono nombre="guardar" tamano="18"/> Guardar entrega</button>
    </form>
    @if($efectivoAdmin !== null)<div class="alerta alerta-advertencia" style="margin-top:14px;">Administración validó/corrigió el efectivo final a <strong>S/ {{ number_format((float)$efectivoAdmin,2) }}</strong>. El valor informado por el operador se conserva.</div>@endif
</section>
@endif

@if($financiero && $puedeCorregirEfectivo)
<section class="tarjeta">
    <div class="tarjeta-cabecera"><div><span class="tarjeta-sobretitulo">Conciliación</span><h2>Validación de efectivo</h2><p class="texto-secundario">Verifica el efectivo informado por el operador y registra el monto validado por administración.</p></div></div>
    <form method="POST" action="{{ route('turnos.corregir-efectivo',$cabecera->id) }}" class="formulario-inline formulario-wrap">
        @csrf @method('PATCH')
        <div class="campo-formulario"><label>Efectivo informado por operador</label><input value="{{ number_format($efectivoOperador,2,'.','') }}" disabled></div>
        <div class="campo-formulario"><label>Efectivo validado por administración *</label><input id="validacion-efectivo-admin" type="number" min="0" step="0.01" name="efectivo_administrador" value="{{ old('efectivo_administrador',number_format((float)($efectivoAdmin ?? $efectivoOperador),2,'.','')) }}" required></div>
        <div class="campo-formulario campo-flex-grow"><label>Observación</label><input name="observacion_validacion_efectivo" value="{{ old('observacion_validacion_efectivo',data_get($financiero,'observacion_validacion_efectivo','')) }}"></div>
        <button class="boton boton-primario">Guardar validación</button>
    </form>
</section>
@endif

@if($financiero && $puedeValidarDigital)
<section class="tarjeta">
    <div class="tarjeta-cabecera"><div><span class="tarjeta-sobretitulo">Conciliación</span><h2>Validación de dinero digital</h2></div></div>
    <form method="POST" action="{{ route('turnos.validar',$cabecera->id) }}" class="formulario-inline formulario-wrap">
        @csrf @method('PATCH')
        <div class="campo-formulario"><label>Digital informado por operador</label><input value="{{ number_format($digitalOperador,2,'.','') }}" disabled></div>
        <div class="campo-formulario"><label>Digital validado por administración *</label><input id="validacion-digital-admin" type="number" step="0.01" min="0" name="digital_administrador" value="{{ old('digital_administrador',data_get($financiero,'digital_administrador')) }}" required></div>
        <div class="campo-formulario"><label>Estado *</label><select name="estado_validacion_digital" required><option value="VALIDADO" @selected((data_get($financiero,'estado_validacion_digital')??'')==='VALIDADO')>Validado</option><option value="OBSERVADO" @selected((data_get($financiero,'estado_validacion_digital')??'')==='OBSERVADO')>Observado</option></select></div>
        <div class="campo-formulario campo-flex-grow"><label>Observación</label><input name="observacion_validacion_digital" value="{{ old('observacion_validacion_digital',data_get($financiero,'observacion_validacion_digital','')) }}"></div>
        <button class="boton boton-primario">Guardar validación</button>
    </form>
</section>
@endif

<script>
(() => {
    const form = document.getElementById('form-entrega');
    if (!form) return;

    const efectivo = document.getElementById('entrega-efectivo');
    const digital = document.getElementById('entrega-digital');
    const total = document.getElementById('entrega-total');
    const diferencia = document.getElementById('entrega-diferencia');
    const estado = document.getElementById('entrega-estado');
    const esperado = Number(form.dataset.esperado || 0);

    const resumen = document.getElementById('resumen-conciliacion-turno');
    const resumenMontoOperador = document.getElementById('resumen-monto-operador');
    const resumenDiferencia = document.getElementById('resumen-diferencia');
    const resumenBase = document.getElementById('resumen-diferencia-base');
    const turnoValidado = resumen?.dataset.turnoValidado === '1';

    const actualizar = () => {
        const e = Number(efectivo.value || 0);
        const d = Number(digital.value || 0);
        const t = e + d;
        const dif = t - esperado;

        total.value = t.toFixed(2);
        diferencia.value = dif.toFixed(2);
        estado.value = Math.abs(dif) < 0.005
            ? 'EXACTO'
            : (dif > 0 ? 'SOBRANTE' : 'FALTANTE');

        if (resumenMontoOperador) {
            resumenMontoOperador.textContent = 'S/ ' + t.toFixed(2);
        }

        // Mientras el turno no este completamente validado, la diferencia
        // principal tambien acompana en vivo la entrega del operador.
        if (!turnoValidado && resumenDiferencia) {
            resumenDiferencia.textContent = 'S/ ' + dif.toFixed(2);
            resumenDiferencia.classList.toggle('diferencia-negativa', dif < 0);
            resumenDiferencia.classList.toggle('diferencia-no-negativa', dif >= 0);
            const cardDiferencia = document.getElementById('resumen-card-diferencia');
            if (cardDiferencia) {
                cardDiferencia.classList.toggle('diferencia-card-negativa', dif < 0);
                cardDiferencia.classList.toggle('diferencia-card-no-negativa', dif >= 0);
            }
            if (resumenBase) {
                resumenBase.textContent = 'Calculada con el monto entregado por el operador.';
            }
        }
    };

    efectivo.addEventListener('input', actualizar);
    digital.addEventListener('input', actualizar);
    actualizar();
})();
</script>
{{-- [PARCHE_ESTADO_ENTREGA_V2_INICIO] --}}
@push('estilos')
<style>
    .estado-entrega-visual {
        text-align: center;
        font-weight: 800;
        letter-spacing: .045em;
        border-width: 1px !important;
        transition: background-color .18s ease, color .18s ease, border-color .18s ease;
    }
    .estado-entrega-visual.estado-faltante {
        color: #b91c1c !important;
        background: #fef2f2 !important;
        border-color: #fecaca !important;
    }
    .estado-entrega-visual.estado-sobrante {
        color: #1d4ed8 !important;
        background: #eff6ff !important;
        border-color: #bfdbfe !important;
    }
    .estado-entrega-visual.estado-exacto {
        color: #15803d !important;
        background: #f0fdf4 !important;
        border-color: #bbf7d0 !important;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function localizarEstado() {
        const etiquetas = Array.from(document.querySelectorAll('label'));
        const etiqueta = etiquetas.find(el => (el.textContent || '').trim().toLowerCase() === 'estado');

        if (etiqueta) {
            const forId = etiqueta.getAttribute('for');
            if (forId) {
                const porId = document.getElementById(forId);
                if (porId) return porId;
            }

            const contenedor = etiqueta.parentElement;
            if (contenedor) {
                const campo = contenedor.querySelector('input, select, textarea');
                if (campo) return campo;
            }
        }

        return document.querySelector('[name="estado"], #estado, [data-estado-entrega]');
    }

    function aplicarEstado() {
        const campo = localizarEstado();
        if (!campo) return;

        const estado = String(campo.value || '').trim().toUpperCase();
        campo.classList.add('estado-entrega-visual');
        campo.classList.remove('estado-faltante', 'estado-sobrante', 'estado-exacto');

        if (estado.includes('FALT')) {
            campo.classList.add('estado-faltante');
        } else if (estado.includes('SOBR')) {
            campo.classList.add('estado-sobrante');
        } else if (estado.includes('EXAC')) {
            campo.classList.add('estado-exacto');
        }
    }

    aplicarEstado();
    document.addEventListener('input', aplicarEstado);
    document.addEventListener('change', aplicarEstado);
});
</script>
@endpush
{{-- [PARCHE_ESTADO_ENTREGA_V2_FIN] --}}
@endsection
