<?php

namespace App\Http\Controllers;

use App\Services\ServicioAuditoria;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControladorReportes extends ControladorBaseGrifo
{
    public function indice(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD','LECTURA']);
        [$desde,$hasta]=$this->periodo($request);
        $diario=DB::table('vw_ventas_diarias')->whereBetween('fecha_operacion',[$desde,$hasta])->orderBy('fecha_operacion')->get();
        $ventas=(float)$diario->sum('ventas_brutas'); $gastosTurno=(float)$diario->sum('gastos_efectivo'); $neto=(float)$diario->sum('neto_esperado'); $diferencia=(float)$diario->sum('diferencia_total');
        $galones=(float)DB::table('totales_producto_turno as tp')->join('turnos_operativos as t','t.id','=','tp.turno_operativo_id')->where('t.establecimiento_id',$this->establecimientoId())->whereBetween('t.fecha_operacion',[$desde,$hasta])->sum('tp.galones');
        $porProducto=DB::table('totales_producto_turno as tp')->join('turnos_operativos as t','t.id','=','tp.turno_operativo_id')->join('productos as p','p.id','=','tp.producto_id')->where('t.establecimiento_id',$this->establecimientoId())->whereBetween('t.fecha_operacion',[$desde,$hasta])->groupBy('p.id','p.codigo','p.nombre')->orderByDesc(DB::raw('SUM(tp.galones)'))->get(['p.codigo','p.nombre',DB::raw('SUM(tp.galones) as galones')]);
        $porTrabajador=DB::table('turnos_operativos as t')->leftJoin('trabajadores as tr','tr.id','=','t.trabajador_id')->leftJoin('resumen_financiero_turno as rf','rf.turno_operativo_id','=','t.id')->where('t.establecimiento_id',$this->establecimientoId())->whereBetween('t.fecha_operacion',[$desde,$hasta])->groupBy('tr.id','tr.nombre_completo')->orderByDesc(DB::raw('SUM(COALESCE(rf.ventas_brutas_combustible,0))'))->get(['tr.nombre_completo as trabajador',DB::raw('COUNT(*) as turnos'),DB::raw('SUM(COALESCE(rf.ventas_brutas_combustible,0)+COALESCE(rf.otros_ingresos,0)+COALESCE(rf.ventas_lubricantes,0)) as ventas'),DB::raw('SUM(COALESCE(rf.diferencia_caja,rf.diferencia_operador,0)) as diferencia')]);
        $maxVenta=max(1,(float)($diario->max('ventas_brutas')??1));
        return view('reportes.indice',compact('desde','hasta','diario','ventas','gastosTurno','neto','diferencia','galones','porProducto','porTrabajador','maxVenta'));
    }

    public function exportar(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD','LECTURA']);[$desde,$hasta]=$this->periodo($request);
        $filas=DB::table('vw_ventas_diarias')->whereBetween('fecha_operacion',[$desde,$hasta])->orderBy('fecha_operacion')->get();
        ServicioAuditoria::registrar('REPORTE_VENTAS',"{$desde}_{$hasta}",'EXPORTAR',null,['desde'=>$desde,'hasta'=>$hasta,'filas'=>$filas->count()],$request);
        $nombre="reporte_grifo_munichis_{$desde}_{$hasta}.csv";
        return response()->streamDownload(function()use($filas){$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Fecha','Ventas brutas','Gastos efectivo','Neto esperado','Diferencia'],';');foreach($filas as $r)fputcsv($out,[$r->fecha_operacion,$r->ventas_brutas,$r->gastos_efectivo,$r->neto_esperado,$r->diferencia_total],';');fclose($out);},$nombre,['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    private function periodo(Request $request): array
    {
        $desde=$request->query('desde',now()->startOfMonth()->toDateString());$hasta=$request->query('hasta',now()->toDateString());if($desde>$hasta)[$desde,$hasta]=[$hasta,$desde];return[$desde,$hasta];
    }
}
