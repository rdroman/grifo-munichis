<?php

namespace App\Http\Controllers;

use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControladorMovimientos extends ControladorBaseGrifo
{
    public function indice(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD','LECTURA']);

        $lecturaCol = Esquema::resolver('lecturas_totalizador', ['lectura','totalizador']);
        $galonesCol = Esquema::resolver('lecturas_totalizador', ['galones_vendidos','galones']);

        $q = DB::table('lecturas_totalizador as l')
            ->join('turnos_operativos as t','t.id','=','l.turno_operativo_id')
            ->leftJoin('tipos_turno as tt','tt.id','=','t.tipo_turno_id')
            ->leftJoin('trabajadores as tr','tr.id','=','t.trabajador_id')
            ->leftJoin('mangueras as m','m.id','=','l.manguera_id')
            ->leftJoin('productos as p','p.id','=','m.producto_id')
            ->leftJoin('surtidores as s','s.id','=','m.surtidor_id')
            ->where('t.establecimiento_id',$this->establecimientoId())
            ->select(['l.id','l.turno_operativo_id','t.fecha_operacion','tt.codigo as turno','tr.nombre_completo as trabajador','m.codigo as manguera','p.codigo as codigo_producto','p.nombre as producto','s.nombre as surtidor']);

        if ($lecturaCol) $q->addSelect("l.{$lecturaCol} as lectura");
        if ($galonesCol) $q->addSelect("l.{$galonesCol} as galones_vendidos");

        $desde = $request->query('desde', now()->subDays(14)->toDateString());
        $hasta = $request->query('hasta', now()->toDateString());
        $producto = $request->query('producto');
        $trabajador = $request->query('trabajador');

        if ($desde) $q->whereDate('t.fecha_operacion','>=',$desde);
        if ($hasta) $q->whereDate('t.fecha_operacion','<=',$hasta);
        if ($producto) $q->where('p.id',$producto);
        if ($trabajador) $q->where('tr.id',$trabajador);

        $baseMetricas = clone $q;
        $movimientos = $q->orderByDesc('t.fecha_operacion')->orderByDesc('l.turno_operativo_id')->orderBy('m.id')->paginate(30)->withQueryString();

        $galones = 0;
        if ($galonesCol) {
            $galones = (float) DB::table('lecturas_totalizador as l')
                ->join('turnos_operativos as t','t.id','=','l.turno_operativo_id')
                ->where('t.establecimiento_id',$this->establecimientoId())
                ->when($desde, fn($x)=>$x->whereDate('t.fecha_operacion','>=',$desde))
                ->when($hasta, fn($x)=>$x->whereDate('t.fecha_operacion','<=',$hasta))
                ->when($producto, fn($x)=>$x->join('mangueras as mm','mm.id','=','l.manguera_id')->where('mm.producto_id',$producto))
                ->sum("l.{$galonesCol}");
        }

        $productos = DB::table('productos')->orderBy('nombre')->get(['id','codigo','nombre']);
        $trabajadores = DB::table('trabajadores')->where('establecimiento_id',$this->establecimientoId())->orderBy('nombre_completo')->get(['id','nombre_completo']);
        $metricas = ['registros'=>$movimientos->total(),'galones'=>$galones];

        return view('movimientos.indice', compact('movimientos','productos','trabajadores','producto','trabajador','desde','hasta','metricas'));
    }
}
