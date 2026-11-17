@php
    $rol = auth()->user()->rol;
    $esOperador = $rol === 'OPERADOR';
    $esSupervisor = $rol === 'SUPERVISOR';

    // Los permisos generales ya no incluyen SUPERVISOR.
    // SUPERVISOR usa su propia lista blanca de menú más abajo.
    $puedeOperarGlobal = in_array($rol, ['ADMINISTRADOR','GERENCIA'], true);
    $puedeFinanzas = in_array($rol, ['ADMINISTRADOR','GERENCIA','CONTABILIDAD'], true);
    $puedeGestionar = in_array($rol, ['ADMINISTRADOR','GERENCIA'], true);
    $puedeAdministrar = $rol === 'ADMINISTRADOR';
@endphp

<nav class="menu-principal" aria-label="Navegación principal">
@if($esOperador)
    <a href="{{ route('turnos.indice') }}" class="menu-enlace {{ request()->routeIs('turnos.*')?'activo':'' }}"><x-icono nombre="turnos"/><span>Mis turnos</span></a>
    <div class="menu-seccion">Mi operación</div>
    <a href="{{ route('creditos.indice') }}" class="menu-enlace {{ request()->routeIs('creditos.*')?'activo':'' }}"><x-icono nombre="creditos"/><span>Créditos del turno</span></a>
    <a href="{{ route('crud.indice','gastos') }}" class="menu-enlace {{ request()->route('modulo')==='gastos'?'activo':'' }}"><x-icono nombre="gastos"/><span>Gastos del turno</span></a>

@elseif($esSupervisor)
    {{--
        Lista blanca de navegación para SUPERVISOR.
        Solo se muestran los módulos autorizados por la política del rol.
    --}}
    <a href="{{ route('panel') }}" class="menu-enlace {{ request()->routeIs('panel')?'activo':'' }}"><x-icono nombre="panel"/><span>Panel</span></a>

    <div class="menu-seccion">Operación</div>
    <a href="{{ route('movimientos.indice') }}" class="menu-enlace {{ request()->routeIs('movimientos.*')?'activo':'' }}"><x-icono nombre="movimientos"/><span>Movimientos</span></a>
    <a href="{{ route('turnos.indice') }}" class="menu-enlace {{ request()->routeIs('turnos.*')?'activo':'' }}"><x-icono nombre="turnos"/><span>Turnos</span></a>
    <a href="{{ route('tanques.indice') }}" class="menu-enlace {{ request()->routeIs('tanques.*')?'activo':'' }}"><x-icono nombre="tanques"/><span>Tanques</span></a>

    <div class="menu-seccion">Administración</div>
    <a href="{{ route('crud.indice','compras') }}" class="menu-enlace {{ request()->route('modulo')==='compras'?'activo':'' }}"><x-icono nombre="compras"/><span>Compras</span></a>
    <a href="{{ route('creditos.indice') }}" class="menu-enlace {{ request()->routeIs('creditos.*')?'activo':'' }}"><x-icono nombre="creditos"/><span>Créditos</span></a>
    <a href="{{ route('crud.indice','gastos') }}" class="menu-enlace {{ request()->route('modulo')==='gastos'?'activo':'' }}"><x-icono nombre="gastos"/><span>Gastos</span></a>
    <a href="{{ route('descuentos.indice') }}" class="menu-enlace {{ request()->routeIs('descuentos.*')?'activo':'' }}"><x-icono nombre="precios"/><span>Descuentos</span></a>

    <div class="menu-seccion">Gestión</div>
    <a href="{{ route('crud.indice','clientes') }}" class="menu-enlace {{ request()->route('modulo')==='clientes'?'activo':'' }}"><x-icono nombre="clientes"/><span>Clientes</span></a>

@else
    <a href="{{ route('panel') }}" class="menu-enlace {{ request()->routeIs('panel')?'activo':'' }}"><x-icono nombre="panel"/><span>Panel</span></a>

    @if($puedeOperarGlobal || $rol==='LECTURA' || $rol==='CONTABILIDAD')
    <div class="menu-seccion">Operación</div>
    <a href="{{ route('movimientos.indice') }}" class="menu-enlace {{ request()->routeIs('movimientos.*')?'activo':'' }}"><x-icono nombre="movimientos"/><span>Movimientos</span></a>
    <a href="{{ route('turnos.indice') }}" class="menu-enlace {{ request()->routeIs('turnos.*')?'activo':'' }}"><x-icono nombre="turnos"/><span>Turnos</span></a>
    <a href="{{ route('tanques.indice') }}" class="menu-enlace {{ request()->routeIs('tanques.*')?'activo':'' }}"><x-icono nombre="tanques"/><span>Tanques</span></a>
    @endif

    @if($puedeFinanzas)
    <div class="menu-seccion">Administración</div>
    <a href="{{ route('crud.indice','compras') }}" class="menu-enlace {{ request()->route('modulo')==='compras'?'activo':'' }}"><x-icono nombre="compras"/><span>Compras</span></a>
    <a href="{{ route('creditos.indice') }}" class="menu-enlace {{ request()->routeIs('creditos.*')?'activo':'' }}"><x-icono nombre="creditos"/><span>Créditos</span></a>
    <a href="{{ route('crud.indice','gastos') }}" class="menu-enlace {{ request()->route('modulo')==='gastos'?'activo':'' }}"><x-icono nombre="gastos"/><span>Gastos</span></a>
    <a href="{{ route('crud.indice','caja') }}" class="menu-enlace {{ request()->route('modulo')==='caja'?'activo':'' }}"><x-icono nombre="dinero"/><span>Caja</span></a>
    <a href="{{ route('crud.indice','descuentos') }}" class="menu-enlace {{ request()->route('modulo')==='descuentos'?'activo':'' }}"><x-icono nombre="precios"/><span>Descuentos</span></a>
    <a href="{{ route('crud.indice','pagos-turno') }}" class="menu-enlace {{ request()->route('modulo')==='pagos-turno'?'activo':'' }}"><x-icono nombre="dinero"/><span>Pagos por turno</span></a>
    <a href="{{ route('crud.indice','ajustes-personal') }}" class="menu-enlace {{ request()->route('modulo')==='ajustes-personal'?'activo':'' }}"><x-icono nombre="trabajadores"/><span>Ajustes personal</span></a>
    @endif

    @if($puedeGestionar)
    <div class="menu-seccion">Gestión</div>
    <a href="{{ route('trabajadores.indice') }}" class="menu-enlace {{ request()->routeIs('trabajadores.*')?'activo':'' }}"><x-icono nombre="trabajadores"/><span>Trabajadores</span></a>
    <a href="{{ route('crud.indice','clientes') }}" class="menu-enlace {{ request()->route('modulo')==='clientes'?'activo':'' }}"><x-icono nombre="clientes"/><span>Clientes</span></a>
    <a href="{{ route('precios.indice') }}" class="menu-enlace {{ request()->routeIs('precios.*')?'activo':'' }}"><x-icono nombre="precios"/><span>Precios</span></a>
    <a href="{{ route('competencia.indice') }}" class="menu-enlace {{ request()->routeIs('competencia.*')?'activo':'' }}"><x-icono nombre="competencia"/><span>Competencia</span></a>
    <a href="{{ route('crud.indice','campanas') }}" class="menu-enlace {{ request()->route('modulo')==='campanas'?'activo':'' }}"><x-icono nombre="reportes"/><span>Campañas</span></a>
    @endif

    <div class="menu-seccion">Análisis</div>
    <a href="{{ route('reportes.indice') }}" class="menu-enlace {{ request()->routeIs('reportes.*')?'activo':'' }}"><x-icono nombre="reportes"/><span>Reportes</span></a>
    @if($puedeFinanzas || $rol==='LECTURA')<a href="{{ route('diferencias.indice') }}" class="menu-enlace {{ request()->routeIs('diferencias.*')?'activo':'' }}"><x-icono nombre="alerta"/><span>Sobrantes / faltantes</span></a>@endif

    @if($puedeAdministrar)
    <div class="menu-seccion">Sistema</div>
    <a href="{{ route('configuracion') }}" class="menu-enlace {{ request()->routeIs('configuracion')||request()->routeIs('usuarios.*')||request()->routeIs('catalogos.*')?'activo':'' }}"><x-icono nombre="configuracion"/><span>Configuración</span></a>
    <a href="{{ route('auditoria') }}" class="menu-enlace {{ request()->routeIs('auditoria')?'activo':'' }}"><x-icono nombre="auditoria"/><span>Auditoría</span></a>
    @endif
@endif
</nav>
