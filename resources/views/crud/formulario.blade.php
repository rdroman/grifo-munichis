@extends('layouts.aplicacion')
@section('titulo', $esEdicion ? 'Editar '.$config['singular'] : 'Nuevo '.$config['singular'])
@section('encabezado', $config['titulo'])
@section('contenido')
@php
    // [PARCHE_RETORNO_TURNO_V2] contexto gastos
    $volverTurnoId = (int) old('volver_turno_id', request()->integer('turno_operativo_id'));
    $volverAlTurno = (($modulo ?? null) === 'gastos') && $volverTurnoId > 0;
@endphp
@php
    $rutaIndice = !empty($esCatalogo) ? 'catalogos.indice' : 'crud.indice';
    $rutaGuardar = !empty($esCatalogo) ? 'catalogos.guardar' : 'crud.guardar';
    $rutaActualizar = !empty($esCatalogo) ? 'catalogos.actualizar' : 'crud.actualizar';
    $valoresPredeterminados = $valoresPredeterminados ?? [];
@endphp
<div class="encabezado-pagina"><a href="{{ route($rutaIndice,$modulo) }}" class="enlace-volver"><x-icono nombre="volver" tamano="17"/> {{ $config['titulo'] }}</a><h1>{{ $esEdicion ? 'Editar '. $config['singular'] : 'Nuevo '. $config['singular'] }}</h1></div>
@if($errors->any())<div class="alerta alerta-error"><x-icono nombre="alerta"/><div><strong>Revisa los datos ingresados.</strong><span>{{ $errors->first() }}</span></div></div>@endif
@if(auth()->user()->rol==='OPERADOR' && $modulo==='gastos')<div class="alerta" style="margin-bottom:16px;"><x-icono nombre="alerta"/><span>El gasto quedará vinculado obligatoriamente al turno seleccionado. La fecha y el trabajador se ajustan al turno al guardar.</span></div>@endif
<section class="tarjeta"><div class="tarjeta-cabecera"><div><span class="tarjeta-sobretitulo">Datos</span><h2>Información del registro</h2></div></div>
<form method="POST" action="{{ $esEdicion ? route($rutaActualizar,[$modulo,$registro->id]) : route($rutaGuardar,$modulo) }}" class="formulario-modulo">@csrf
    @if($volverAlTurno)<input type="hidden" name="volver_turno_id" value="{{ $volverTurnoId }}">@endif @if($esEdicion)@method('PUT')@endif
<div class="rejilla-campos">
@foreach($campos as $campo)
@php
    $nombre=$campo['columna'];
    $valor=old($nombre,$registro->{$nombre} ?? ($valoresPredeterminados[$nombre] ?? null));
    $tipo=$campo['tipo'];
@endphp
<div class="campo-formulario {{ $tipo==='textarea' ? 'campo-span-2':'' }}"><label for="{{ $nombre }}">{{ $campo['etiqueta'] }} @if($campo['required']??false)<span class="requerido">*</span>@endif</label>
@if($tipo==='textarea')<textarea id="{{ $nombre }}" name="{{ $nombre }}" rows="4">{{ $valor }}</textarea>
@elseif($tipo==='bool')<input type="hidden" name="{{ $nombre }}" value="0"><label class="gm-switch" for="{{ $nombre }}"><input id="{{ $nombre }}" name="{{ $nombre }}" type="checkbox" value="1" @checked((bool)$valor)><span class="gm-switch__slider"></span><span class="gm-switch__text">Activo / habilitado</span></label>
@elseif($tipo==='enum')<select id="{{ $nombre }}" name="{{ $nombre }}" @required($campo['required']??false)><option value="">Seleccione</option>@foreach($campo['valores'] ?? [] as $op)<option value="{{ $op }}" @selected((string)$valor===(string)$op)>{{ \App\Support\Esquema::etiquetaEnum($op) }}</option>@endforeach</select>
@elseif(in_array($tipo,['fk','turno'],true))<select id="{{ $nombre }}" name="{{ $nombre }}" @required($campo['required']??false)><option value="">Seleccione</option>@foreach($opciones[$nombre] ?? [] as $id=>$texto)<option value="{{ $id }}" @selected((string)$valor===(string)$id)>{{ $texto }}</option>@endforeach</select>
@else<input id="{{ $nombre }}" name="{{ $nombre }}" type="{{ $tipo }}" value="{{ $valor }}" @if(isset($campo['step'])) step="{{ $campo['step'] }}" @endif @required($campo['required']??false)>
@endif
@error($nombre)<small class="error-campo">{{ $message }}</small>@enderror</div>
@endforeach
</div><div class="acciones-formulario"><a class="boton boton-secundario" href="{{ route($rutaIndice,$modulo) }}">Cancelar</a><button class="boton boton-primario"><x-icono nombre="guardar" tamano="18"/> Guardar</button></div>
</form></section>

@if($volverAlTurno)
<script>
document.addEventListener('DOMContentLoaded', function () {
    // [PARCHE_RETORNO_TURNO_V2] navegacion gastos
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
