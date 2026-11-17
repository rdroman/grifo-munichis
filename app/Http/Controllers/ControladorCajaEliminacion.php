<?php

namespace App\Http\Controllers;

use App\Services\ServicioAuditoria;
use App\Services\ServicioRecalculoCaja;
use App\Services\ServicioSaldoEsperadoCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ControladorCajaEliminacion extends ControladorBaseGrifo
{
    public function eliminar(Request $request, int $id, ServicioRecalculoCaja $recalculo, ServicioSaldoEsperadoCaja $saldos)
    {
        $this->autorizar(['ADMINISTRADOR']);
        $datos = $request->validate([
            'motivo_eliminacion' => ['required','string','min:5','max:500'],
        ]);

        abort_unless(Schema::hasTable('controles_diarios_caja'), 404);
        $fechaCol = $this->columna(['fecha_control','fecha']);

        $registro = DB::table('controles_diarios_caja')
            ->where('id',$id)
            ->where('establecimiento_id',$this->establecimientoId())
            ->first();
        abort_unless($registro,404);

        $fecha = $fechaCol ? (string)data_get($registro,$fechaCol) : now('America/Lima')->toDateString();
        $motivo = trim($datos['motivo_eliminacion']);
        $posteriores = $fechaCol
            ? DB::table('controles_diarios_caja')->where('establecimiento_id',$this->establecimientoId())->where($fechaCol,'>',$fecha)->count()
            : 0;

        DB::transaction(function () use ($request,$registro,$id,$motivo,$fecha,$recalculo,$saldos) {
            DB::table('controles_diarios_caja_eliminados')->insert([
                'control_original_id' => $id,
                'establecimiento_id' => $this->establecimientoId(),
                'fecha_control' => $fecha,
                'registro_json' => json_encode((array)$registro, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'motivo_eliminacion' => $motivo,
                'usuario_id' => auth()->id(),
                'eliminado_en' => now('America/Lima'),
            ]);

            ServicioAuditoria::registrar(
                'CONTROL_CAJA',
                $id,
                'ELIMINAR',
                (array)$registro,
                ['motivo_eliminacion'=>$motivo,'archivado'=>true],
                $request
            );

            DB::table('controles_diarios_caja')->where('id',$id)->delete();
            $recalculo->recalcularDesde($this->establecimientoId(), $fecha);
            // Sobrescribe las dependencias calculadas desde efectivo físico
            // por la cadena contable basada en efectivo ESPERADO.
            $saldos->recalcularPosteriores($this->establecimientoId(), $fecha, $request);
        });

        $mensaje = 'Control diario de caja eliminado y archivado en auditoría.';
        if ($posteriores > 0) $mensaje .= " Se recalcularon los controles posteriores dependientes.";
        return back()->with('exito',$mensaje);
    }

    private function columna(array $candidatas): ?string
    {
        $cols=array_flip(Schema::getColumnListing('controles_diarios_caja'));
        foreach($candidatas as $c) if(isset($cols[$c])) return $c;
        return null;
    }
}
