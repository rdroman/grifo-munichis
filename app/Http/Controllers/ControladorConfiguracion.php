<?php

namespace App\Http\Controllers;

use App\Support\Esquema;
use Illuminate\Support\Facades\DB;

class ControladorConfiguracion extends ControladorBaseGrifo
{
    public function indice()
    {
        $this->autorizar(['ADMINISTRADOR']);
        $catalogos=[];
        foreach(config('grifo.catalogos',[]) as $clave=>$cfg){
            if(Esquema::existeTabla($cfg['tabla'])){
                $q=DB::table($cfg['tabla']);
                if(Esquema::tiene($cfg['tabla'],'establecimiento_id'))$q->where('establecimiento_id',$this->establecimientoId());
                $catalogos[]=['clave'=>$clave,'titulo'=>$cfg['titulo'],'tabla'=>$cfg['tabla'],'total'=>$q->count()];
            }
        }
        $usuarios=DB::table('usuarios')->where('establecimiento_id',$this->establecimientoId())->count();
        return view('configuracion.indice',compact('catalogos','usuarios'));
    }
}
