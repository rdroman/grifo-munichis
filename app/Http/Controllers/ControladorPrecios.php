<?php

namespace App\Http\Controllers;

use App\Services\ServicioAuditoria;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ControladorPrecios extends ControladorBaseGrifo
{
    public function indice(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD','LECTURA']);
        $vigentes = Esquema::existeTabla('vw_precios_venta_vigentes')
            ? DB::table('vw_precios_venta_vigentes as v')->join('productos as p','p.id','=','v.producto_id')->where('v.establecimiento_id',$this->establecimientoId())->where('p.activo',1)->select('v.*')->orderBy('v.producto_id')->get()
            : collect();

        $historial = DB::table('historial_precios as h')
            ->join('productos as p','p.id','=','h.producto_id')
            ->leftJoin('turnos_operativos as t','t.id','=','h.turno_operativo_desde_id')
            ->leftJoin('tipos_turno as tt','tt.id','=','t.tipo_turno_id')
            ->where('h.establecimiento_id',$this->establecimientoId())
            ->where('h.tipo_precio','VENTA')
            ->select('h.*','p.codigo as producto_codigo','p.nombre as producto','t.fecha_operacion','tt.codigo as turno_codigo')
            ->orderByDesc('h.vigente_desde')->orderByDesc('h.id')->paginate(30)->withQueryString();

        $productos = DB::table('productos')->where('categoria','COMBUSTIBLE')->where('activo',1)->orderBy('id')->get();
        $turnos = DB::table('turnos_operativos as t')->join('tipos_turno as tt','tt.id','=','t.tipo_turno_id')->leftJoin('trabajadores as tr','tr.id','=','t.trabajador_id')->where('t.establecimiento_id',$this->establecimientoId())->orderByDesc('t.fecha_operacion')->orderByDesc('t.id')->limit(300)->get(['t.id','t.fecha_operacion','t.tipo_turno_id','tt.codigo','tt.nombre','tt.hora_inicio','tr.nombre_completo']);

        return view('precios.indice',compact('vigentes','historial','productos','turnos'));
    }

    public function guardar(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR']);
        $request->validate([
            'producto_id'=>['required',Rule::exists('productos','id')],
            'precio'=>['required','numeric','gt:0'],
            'turno_operativo_desde_id'=>['required',Rule::exists('turnos_operativos','id')],
            'observaciones'=>['nullable','string','max:255'],
        ]);

        $turno = DB::table('turnos_operativos as t')->join('tipos_turno as tt','tt.id','=','t.tipo_turno_id')->where('t.id',$request->turno_operativo_desde_id)->where('t.establecimiento_id',$this->establecimientoId())->select('t.*','tt.codigo','tt.hora_inicio')->first();
        abort_unless($turno,404);

        $fechaHora = Carbon::parse($turno->fecha_operacion.' '.($turno->hora_inicio ?: '00:00:00'));
        $datos=[
            'establecimiento_id'=>$this->establecimientoId(),
            'producto_id'=>(int)$request->producto_id,
            'tipo_precio'=>'VENTA',
            'precio'=>(float)$request->precio,
            'vigente_desde'=>$fechaHora,
            'turno_operativo_desde_id'=>(int)$request->turno_operativo_desde_id,
            'vigente_hasta'=>null,
            'fuente'=>'SISTEMA',
            'observaciones'=>$request->observaciones ?: 'Registrado desde el sistema web',
            'creado_en'=>now(),
        ];

        $id=DB::transaction(function() use($datos,$request,$fechaHora){
            if(Esquema::tiene('historial_precios','vigente_hasta')){
                DB::table('historial_precios')->where('establecimiento_id',$this->establecimientoId())->where('producto_id',$request->producto_id)->where('tipo_precio','VENTA')->whereNull('vigente_hasta')->update(['vigente_hasta'=>$fechaHora]);
            }
            $id=DB::table('historial_precios')->insertGetId(Esquema::filtrar('historial_precios',$datos));
            ServicioAuditoria::registrar('PRECIO_VENTA',$id,'CREAR',null,$datos,$request);
            return $id;
        });
        return back()->with('exito','Nuevo precio registrado desde el turno real seleccionado.');
    }
}
