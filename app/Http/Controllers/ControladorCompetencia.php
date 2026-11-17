<?php

namespace App\Http\Controllers;

use App\Services\ServicioAuditoria;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ControladorCompetencia extends ControladorBaseGrifo
{
    public function indice()
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD','LECTURA']);
        $grifos=DB::table('grifos_competidores')->orderBy('nombre')->get();
        $productos=DB::table('productos')->where('categoria','COMBUSTIBLE')->orderBy('id')->get();
        $obsCol=Esquema::resolver('precios_competencia',['observado_en','fecha_observacion','registrado_en']);
        $precios=DB::table('precios_competencia as pc')->join('grifos_competidores as g','g.id','=','pc.grifo_competidor_id')->join('productos as p','p.id','=','pc.producto_id')->select('pc.*','g.nombre as grifo','p.codigo as producto_codigo','p.nombre as producto')->orderByDesc($obsCol ? 'pc.'.$obsCol : 'pc.id')->limit(100)->get();
        return view('competencia.indice',compact('grifos','productos','precios','obsCol'));
    }

    public function guardarGrifo(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR']);
        $request->validate(['nombre'=>['required','string','max:180'],'distrito'=>['nullable','string','max:100'],'direccion'=>['nullable','string','max:255'],'codigo_osinergmin'=>['nullable','string','max:50'],'url_fuente'=>['nullable','string','max:500']]);
        $datos=Esquema::filtrar('grifos_competidores',['nombre'=>trim($request->nombre),'distrito'=>$request->distrito,'direccion'=>$request->direccion,'codigo_osinergmin'=>$request->codigo_osinergmin,'url_fuente'=>$request->url_fuente,'activo'=>1]);
        $id=DB::table('grifos_competidores')->insertGetId($datos);ServicioAuditoria::registrar('GRIFO_COMPETIDOR',$id,'CREAR',null,$datos,$request);return back()->with('exito','Competidor registrado.');
    }

    public function guardarPrecio(Request $request)
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR']);
        $request->validate(['grifo_competidor_id'=>['required',Rule::exists('grifos_competidores','id')],'producto_id'=>['required',Rule::exists('productos','id')],'precio'=>['required','numeric','gt:0'],'fuente'=>['nullable','string','max:30'],'observado_en'=>['nullable','date']]);
        $obsCol=Esquema::resolver('precios_competencia',['observado_en','fecha_observacion','registrado_en']);
        $datos=['grifo_competidor_id'=>$request->grifo_competidor_id,'producto_id'=>$request->producto_id,'precio'=>(float)$request->precio,'fuente'=>$request->fuente?:'MANUAL'];if($obsCol)$datos[$obsCol]=$request->observado_en?:now();
        $datos=Esquema::filtrar('precios_competencia',$datos);$id=DB::table('precios_competencia')->insertGetId($datos);ServicioAuditoria::registrar('PRECIO_COMPETENCIA',$id,'CREAR',null,$datos,$request);return back()->with('exito','Precio de competencia registrado.');
    }
}
