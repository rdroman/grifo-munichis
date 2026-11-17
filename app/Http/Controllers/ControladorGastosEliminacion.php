<?php

namespace App\Http\Controllers;

use App\Services\ServicioAuditoria;
use App\Services\ServicioFinancieroTurno;
use App\Services\ServicioSaldoEsperadoCaja;
use App\Support\Esquema;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControladorGastosEliminacion extends ControladorBaseGrifo
{
    public function confirmar(Request $request, int $id)
    {
        $this->autorizar(['ADMINISTRADOR']);

        return view('gastos.confirmar-eliminacion', [
            'gasto' => $this->consultaGasto($id)->firstOrFail(),
            'columnaMonto' => $this->columnaMonto(),
            'filtros' => $request->only(['q', 'desde', 'hasta', 'page']),
        ]);
    }

    public function eliminar(
        Request $request,
        int $id,
        ControladorCaja $caja,
        ServicioSaldoEsperadoCaja $saldos,
        ServicioFinancieroTurno $finanzas
    ) {
        $this->autorizar(['ADMINISTRADOR']);
        $establecimientoId = $this->establecimientoId();

        DB::transaction(function () use ($id, $request, $caja, $saldos, $finanzas, $establecimientoId) {
            DB::table('establecimientos')->where('id', $establecimientoId)->lockForUpdate()->firstOrFail();

            $consulta = $this->consultaGasto($id);
            $gasto = $consulta->lockForUpdate()->firstOrFail();
            $fecha = (string) $gasto->fecha_gasto;
            $turnoId = (int) $gasto->turno_operativo_id;

            if ($turnoId > 0) {
                DB::table('turnos_operativos')
                    ->where('establecimiento_id', $establecimientoId)
                    ->where('id', $turnoId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $consulta->delete();
                $this->actualizarGastosTurno($turnoId, $finanzas);
            } else {
                $gastosCajaAntes = $caja->totalGastosEfectivoNoAsignados($fecha);
                $consulta->delete();
                $caja->sincronizarGastosEliminados($fecha, $gastosCajaAntes, $saldos, $request);
            }

            ServicioAuditoria::registrar('gastos', $id, 'ELIMINAR', (array) $gasto, null, $request);
        }, 3);

        return redirect()->route('crud.indice', ['modulo' => 'gastos'] + $request->only(['q', 'desde', 'hasta']))
            ->with('exito', 'Gasto eliminado correctamente.');
    }

    private function consultaGasto(int $id): Builder
    {
        return DB::table('gastos')
            ->where('establecimiento_id', $this->establecimientoId())
            ->where('id', $id);
    }

    private function columnaMonto(): string
    {
        $columna = Esquema::resolver('gastos', ['monto', 'importe', 'costo', 'total']);
        abort_unless($columna, 500, 'La columna del importe del gasto no está disponible.');
        return $columna;
    }

    private function actualizarGastosTurno(int $turnoId, ServicioFinancieroTurno $finanzas): void
    {
        $consulta = DB::table('resumen_financiero_turno')->where('turno_operativo_id', $turnoId);
        $resumen = $consulta->lockForUpdate()->first();
        if ($resumen === null) {
            $finanzas->sincronizar($turnoId);
            return;
        }

        $gastos = round((float) DB::table('gastos')
            ->where('establecimiento_id', $this->establecimientoId())
            ->where('turno_operativo_id', $turnoId)
            ->sum($this->columnaMonto()), 2);

        // Ajusta el neto histórico por la variación de gastos, sin reconstruir ventas.
        $variacionNeto = round((float) $resumen->gastos_efectivo - $gastos, 2);
        $datos = [
            'gastos_efectivo' => $gastos,
            'neto_esperado' => round((float) $resumen->neto_esperado + $variacionNeto, 2),
        ];
        foreach (['diferencia_operador', 'diferencia_caja'] as $columna) {
            if (isset($resumen->{$columna})) {
                $datos[$columna] = round((float) $resumen->{$columna} - $variacionNeto, 2);
            }
        }
        $consulta->update($datos);
    }
}
