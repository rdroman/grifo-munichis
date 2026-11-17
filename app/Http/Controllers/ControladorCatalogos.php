<?php

namespace App\Http\Controllers;

use App\Services\ServicioAuditoria;
use App\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ControladorCatalogos extends ControladorBaseGrifo
{
    public function indice(string $catalogo)
    {
        [$config,$campos]=$this->preparar($catalogo);$this->autorizar(['ADMINISTRADOR']);$tabla=$config['tabla'];
        $q=DB::table($tabla);if(Esquema::tiene($tabla,'establecimiento_id'))$q->where('establecimiento_id',$this->establecimientoId());
        $registros=$q->orderBy(Esquema::resolver($tabla,['nombre','codigo','id'])??'id')->paginate(30);
        $opciones=$this->opciones($campos);
        return view('crud.indice', ['modulo'=>$catalogo,'config'=>$config+['descripcion'=>'Catálogo maestro del sistema.'],'campos'=>$campos,'registros'=>$registros,'opciones'=>$opciones,'q'=>'','fechaDesde'=>null,'fechaHasta'=>null,'campoFecha'=>null,'total'=>$registros->total(),'esCatalogo'=>true]);
    }
    public function crear(string $catalogo){[$config,$campos]=$this->preparar($catalogo);$this->autorizar(['ADMINISTRADOR']);return view('crud.formulario',['modulo'=>$catalogo,'config'=>$config,'campos'=>$campos,'opciones'=>$this->opciones($campos),'registro'=>null,'esEdicion'=>false,'esCatalogo'=>true]);}
    public function guardar(Request $request,string $catalogo)
    {
        [$config,$campos]=$this->preparar($catalogo);
        $this->autorizar(['ADMINISTRADOR']);
        $tabla=$config['tabla'];
        $datos=$this->validar($request,$campos);
        if(Esquema::tiene($tabla,'establecimiento_id')) $datos['establecimiento_id']=$this->establecimientoId();
        $datos=Esquema::agregarCreadoEn($tabla,Esquema::filtrar($tabla,$datos));

        $meta=Esquema::columnas($tabla)['id']??null;
        $esAutoincremental=$meta && str_contains(strtolower((string)$meta['extra']),'auto_increment');
        if($esAutoincremental){
            $id=DB::table($tabla)->insertGetId($datos);
        } else {
            if(!array_key_exists('id',$datos)) $datos['id']=(int)(DB::table($tabla)->max('id')??0)+1;
            DB::table($tabla)->insert($datos);
            $id=(int)$datos['id'];
        }

        ServicioAuditoria::registrar($tabla,$id,'CREAR',null,$datos,$request);
        return redirect()->route('catalogos.editar',[$catalogo,$id])->with('exito','Registro creado.');
    }
    public function editar(string $catalogo,int $id){[$config,$campos]=$this->preparar($catalogo);$this->autorizar(['ADMINISTRADOR']);$registro=$this->registro($config['tabla'],$id);return view('crud.formulario',['modulo'=>$catalogo,'config'=>$config,'campos'=>$campos,'opciones'=>$this->opciones($campos),'registro'=>$registro,'esEdicion'=>true,'esCatalogo'=>true]);}
    public function actualizar(Request $request,string $catalogo,int $id){[$config,$campos]=$this->preparar($catalogo);$this->autorizar(['ADMINISTRADOR']);$tabla=$config['tabla'];$antes=(array)$this->registro($tabla,$id);$datos=Esquema::agregarActualizadoEn($tabla,Esquema::filtrar($tabla,$this->validar($request,$campos)));DB::table($tabla)->where('id',$id)->update($datos);ServicioAuditoria::registrar($tabla,$id,'ACTUALIZAR',$antes,$datos,$request);return back()->with('exito','Cambios guardados.');}
    private function preparar(string $catalogo):array{$config=config("grifo.catalogos.{$catalogo}");abort_unless(is_array($config),404);abort_unless(Esquema::existeTabla($config['tabla']),404);$campos=[];foreach($config['campos'] as $campo){if($col=Esquema::resolver($config['tabla'],$campo['columnas'])){$campo['columna']=$col;$campo['nombre']=$col;if(($campo['tipo']??'')==='enum')$campo['valores']=Esquema::enum($config['tabla'],$col);$campos[]=$campo;}}return[$config,$campos];}
    private function validar(Request $request,array $campos):array{$reglas=[];foreach($campos as $c){$r=[($c['required']??false)?'required':'nullable'];if($c['tipo']==='number')$r[]='numeric';if($c['tipo']==='date')$r[]='date';if($c['tipo']==='bool')$r[]='boolean';if($c['tipo']==='enum'&&!empty($c['valores']))$r[]=Rule::in($c['valores']);if($c['tipo']==='fk')$r[]=Rule::exists($c['opciones']['tabla'],'id');$reglas[$c['columna']]=$r;}$d=$request->validate($reglas);foreach($campos as $c)if($c['tipo']==='bool')$d[$c['columna']]=$request->boolean($c['columna']);return$d;}
    private function registro(string $tabla,int $id):object{$q=DB::table($tabla)->where('id',$id);if(Esquema::tiene($tabla,'establecimiento_id'))$q->where('establecimiento_id',$this->establecimientoId());return$q->firstOrFail();}
    private function opciones(array $campos):array{$out=[];foreach($campos as $c){if($c['tipo']!=='fk')continue;$cfg=$c['opciones'];$rows=DB::table($cfg['tabla'])->get();$out[$c['columna']]=$rows->mapWithKeys(function($r)use($cfg){$txt=[];foreach($cfg['texto'] as $x)if(isset($r->{$x}))$txt[]=$r->{$x};return[$r->id=>implode(' · ',$txt)];})->all();}return$out;}
}
