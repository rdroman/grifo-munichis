@extends('layouts.aplicacion')
@section('titulo',$descuento ? 'Editar descuento' : 'Registrar descuento')
@section('encabezado','Descuentos')
@section('contenido')
@php
    // [PARCHE_RETORNO_TURNO_V2] contexto descuentos
    $volverTurnoId = (int) old('volver_turno_id', request()->integer('turno_operativo_id'));
    $volverAlTurno = (true) && $volverTurnoId > 0;
@endphp

<div class="encabezado-pagina">
    <a href="{{ route('descuentos.indice') }}" class="enlace-volver"><x-icono nombre="volver" tamano="17"/> Descuentos</a>
    <h1>{{ $descuento ? 'Editar descuento' : 'Registrar descuento' }}</h1>
    <p class="texto-secundario">El importe se calcula automáticamente: galones × precio de descuento.</p>
</div>

@if($errors->any())<div class="alerta alerta-error">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ $descuento ? route('descuentos.actualizar',$descuento->id) : route('descuentos.guardar') }}" class="formulario-modulo" id="form-descuento">
    @csrf
    @if($volverAlTurno)<input type="hidden" name="volver_turno_id" value="{{ $volverTurnoId }}">@endif
    @if($descuento) @method('PUT') @endif

    <section class="tarjeta">
        <div class="tarjeta-cabecera"><div><span class="tarjeta-sobretitulo">Datos</span><h2>Detalle del descuento</h2></div></div>
        <div class="rejilla-campos">
            <div class="campo-formulario campo-span-2">
                <label>Turno *</label>
                <select name="turno_operativo_id" id="turno_operativo_id" required>
                    <option value="">Seleccione</option>
                    @foreach($turnos as $t)
                        @php $sel=old('turno_operativo_id',$descuento->turno_operativo_id ?? $turnoPreseleccionado); @endphp
                        <option value="{{ $t->id }}" @selected((string)$sel===(string)$t->id)>
                            {{ \Carbon\Carbon::parse($t->fecha_operacion)->format('d/m/Y') }} · {{ $t->turno_codigo }} · {{ $t->trabajador ?: 'Sin operador' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="campo-formulario">
                <label>Producto *</label>
                <select name="producto_id" id="producto_id" required>
                    <option value="">Seleccione</option>
                    @foreach($productos as $p)
                        <option value="{{ $p->id }}" @selected((string)old('producto_id',$descuento->producto_id ?? '')===(string)$p->id)>{{ $p->codigo }} · {{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="campo-formulario">
                <label>Galones *</label>
                <input type="number" min="0.001" step="0.001" name="galones" id="galones" value="{{ old('galones',$descuento->galones ?? '') }}" required>
            </div>

            <div class="campo-formulario">
                <label>Precio de descuento (S/ por galón) *</label>
                <input type="number" min="0" step="0.0001" name="precio_unitario_descuento" id="precio_unitario_descuento"
                    value="{{ old('precio_unitario_descuento',$descuento->precio_unitario_descuento ?? '') }}"
                    @readonly(!$esAdministrador) required>
                <small class="texto-secundario">
                    @if($esAdministrador) Precio vigente automático; ADMINISTRADOR puede corregirlo si corresponde. @else Precio vigente automático; no modificable para este rol. @endif
                </small>
            </div>

            <div class="campo-formulario">
                <label>Importe (S/)</label>
                <input type="text" id="importe_visual" value="0.00" readonly>
            </div>

            <div class="campo-formulario campo-span-2">
                <label>Motivo</label>
                <input type="text" maxlength="255" name="motivo" value="{{ old('motivo',$descuento->motivo ?? '') }}" placeholder="Motivo o referencia del descuento">
            </div>
        </div>
        <div id="aviso-precio" class="texto-secundario" style="margin-top:12px;"></div>
    </section>

    <div class="acciones-formulario acciones-persistentes">
        <a class="boton boton-secundario" href="{{ route('descuentos.indice') }}">Cancelar</a>
        <button class="boton boton-primario"><x-icono nombre="guardar" tamano="18"/> Guardar descuento</button>
    </div>
</form>

<script>
(() => {
    const turno = document.getElementById('turno_operativo_id');
    const producto = document.getElementById('producto_id');
    const galones = document.getElementById('galones');
    const precio = document.getElementById('precio_unitario_descuento');
    const importe = document.getElementById('importe_visual');
    const aviso = document.getElementById('aviso-precio');
    const esAdministrador = @json($esAdministrador);
    const urlPrecio = @json(route('descuentos.precio'));

    const calcularImporte = () => {
        const g = parseFloat(galones.value || '0');
        const p = parseFloat(precio.value || '0');
        importe.value = (g * p).toFixed(2);
    };

    const cargarPrecio = async () => {
        if (!turno.value || !producto.value) {
            if (!esAdministrador) precio.value = '';
            aviso.textContent = '';
            calcularImporte();
            return;
        }
        aviso.textContent = 'Consultando precio vigente…';
        try {
            const u = new URL(urlPrecio, window.location.origin);
            u.searchParams.set('turno_operativo_id', turno.value);
            u.searchParams.set('producto_id', producto.value);
            const r = await fetch(u, {headers:{'Accept':'application/json'}});
            const data = await r.json();
            if (!r.ok || !data.ok) throw new Error(data.mensaje || 'No se pudo obtener el precio vigente.');
            precio.value = data.precio;
            aviso.textContent = 'Precio vigente cargado automáticamente: S/ ' + Number(data.precio).toFixed(4) + ' por galón.';
            calcularImporte();
        } catch (e) {
            if (!esAdministrador) precio.value = '';
            aviso.textContent = e.message;
            calcularImporte();
        }
    };

    turno.addEventListener('change', cargarPrecio);
    producto.addEventListener('change', cargarPrecio);
    galones.addEventListener('input', calcularImporte);
    precio.addEventListener('input', calcularImporte);

    calcularImporte();
    @if(!$descuento)
    if (turno.value && producto.value) cargarPrecio();
    @endif
})();
</script>

@if($volverAlTurno)
<script>
document.addEventListener('DOMContentLoaded', function () {
    // [PARCHE_RETORNO_TURNO_V2] navegacion descuentos
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
