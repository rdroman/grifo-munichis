<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControladorAuditoria extends ControladorBaseGrifo
{
    public function indice(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR']);
        $q=DB::table('bitacora_auditoria as b')->leftJoin('usuarios as u','u.id','=','b.usuario_id')->where(function($x){$x->whereNull('u.establecimiento_id')->orWhere('u.establecimiento_id',$this->establecimientoId());})->select('b.*','u.nombre as usuario');
        $accion=$request->query('accion');$entidad=trim((string)$request->query('entidad',''));$desde=$request->query('desde',now()->subDays(30)->toDateString());$hasta=$request->query('hasta',now()->toDateString());
        if($accion)$q->where('b.accion',$accion);if($entidad)$q->where('b.tipo_entidad','like',"%{$entidad}%");if($desde)$q->whereDate('b.creado_en','>=',$desde);if($hasta)$q->whereDate('b.creado_en','<=',$hasta);
        $registros=$q->orderByDesc('b.id')->paginate(30)->withQueryString();
        $acciones=DB::table('bitacora_auditoria')->distinct()->orderBy('accion')->pluck('accion');
        return view('auditoria.indice',compact('registros','acciones','accion','entidad','desde','hasta'));
    }
}
