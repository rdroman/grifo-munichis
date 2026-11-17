<?php

namespace App\Services;

use App\Support\Esquema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioFinancieroTurno
{
    public function __construct(private ServicioPrecioVenta $servicioPrecioVenta)
    {
    }

    public function calcular(int $turnoId): array
    {
        $turno = DB::table('turnos_operativos')
            ->where('id', $turnoId)
            ->first();

        if (! $turno) {
            throw ValidationException::withMessages([
                'turno' => 'El turno indicado no existe.',
            ]);
        }

        $establecimientoId = (int) $turno->establecimiento_id;
        $inconsistencias = [];

        $despachos = DB::table('lecturas_totalizador as l')
            ->join('mangueras as m', 'm.id', '=', 'l.manguera_id')
            ->where('l.turno_operativo_id', $turnoId)
            ->groupBy('m.producto_id')
            ->select(
                'm.producto_id',
                DB::raw('ROUND(SUM(COALESCE(l.galones_vendidos,0)),3) as galones')
            )
            ->get()
            ->keyBy('producto_id');

        $creditos = collect();
        if (Esquema::existeTabla('ventas_credito') && Esquema::existeTabla('detalle_ventas_credito')) {
            $colCantidad = Esquema::resolver('detalle_ventas_credito', ['cantidad_gal','cantidad_galones','galones','cantidad']);
            $colPrecio = Esquema::resolver('detalle_ventas_credito', ['precio_unitario','precio']);
            $colImporte = Esquema::resolver('detalle_ventas_credito', ['importe','importe_total','total']);

            if ($colCantidad) {
                $importeCalculado = $colPrecio
                    ? "COALESCE(d.`{$colCantidad}`,0) * COALESCE(d.`{$colPrecio}`,0)"
                    : '0';
                $expImporte = $colImporte
                    ? "COALESCE(d.`{$colImporte}`, {$importeCalculado}, 0)"
                    : $importeCalculado;

                $creditos = DB::table('detalle_ventas_credito as d')
                    ->join('ventas_credito as v', 'v.id', '=', 'd.venta_credito_id')
                    ->where('v.turno_operativo_id', $turnoId)
                    ->groupBy('d.producto_id')
                    ->select(
                        'd.producto_id',
                        DB::raw("ROUND(SUM(COALESCE(d.`{$colCantidad}`,0)),3) as galones"),
                        DB::raw("ROUND(SUM({$expImporte}),2) as importe")
                    )
                    ->get()
                    ->keyBy('producto_id');
            }
        }

        $descuentos = collect();
        if (Esquema::existeTabla('descuentos')) {
            $descuentos = DB::table('descuentos')
                ->where('turno_operativo_id', $turnoId)
                ->groupBy('producto_id')
                ->select(
                    'producto_id',
                    DB::raw('ROUND(SUM(COALESCE(galones,0)),3) as galones'),
                    DB::raw('ROUND(SUM(COALESCE(importe_efectivo, COALESCE(galones,0) * COALESCE(precio_unitario_descuento,0), 0)),2) as importe')
                )
                ->get()
                ->keyBy('producto_id');
        }

        $idsProducto = collect()
            ->merge($despachos->keys())
            ->merge($creditos->keys())
            ->merge($descuentos->keys())
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $productosCatalogo = $idsProducto->isEmpty()
            ? collect()
            : DB::table('productos')->whereIn('id', $idsProducto)->get()->keyBy('id');

        $productos = [];
        $totalVentaNormal = 0.0;
        $totalDescuentos = 0.0;
        $totalCreditos = 0.0;
        $totalNetoCombustible = 0.0;

        foreach ($idsProducto as $productoId) {
            $catalogo = $productosCatalogo->get($productoId);
            $galonesDespachados = round((float) ($despachos->get($productoId)->galones ?? 0), 3);
            $galonesCredito = round((float) ($creditos->get($productoId)->galones ?? 0), 3);
            $galonesDescuento = round((float) ($descuentos->get($productoId)->galones ?? 0), 3);
            $galonesVendidos = round($galonesDespachados - $galonesCredito - $galonesDescuento, 3);

            $precioObj = $this->servicioPrecioVenta->obtenerParaTurno(
                $establecimientoId,
                $productoId,
                $turnoId
            );
            $precioVigente = $precioObj ? (float) $precioObj->precio : null;

            if ($galonesVendidos < -0.0005) {
                $inconsistencias[] = sprintf(
                    '%s: créditos + descuentos (%.3f gal) superan el despacho físico (%.3f gal).',
                    $catalogo->codigo ?? ('Producto '.$productoId),
                    $galonesCredito + $galonesDescuento,
                    $galonesDespachados
                );
            }

            if (abs($galonesVendidos) >= 0.0005 && $precioVigente === null) {
                $inconsistencias[] = sprintf(
                    '%s: no existe precio de venta vigente para el turno.',
                    $catalogo->codigo ?? ('Producto '.$productoId)
                );
            }

            $importeVentaNormal = round($galonesVendidos * (float) ($precioVigente ?? 0), 2);
            $importeDescuentos = round((float) ($descuentos->get($productoId)->importe ?? 0), 2);
            $importeCreditos = round((float) ($creditos->get($productoId)->importe ?? 0), 2);
            $importeNeto = round($importeVentaNormal + $importeDescuentos, 2);

            $totalVentaNormal += $importeVentaNormal;
            $totalDescuentos += $importeDescuentos;
            $totalCreditos += $importeCreditos;
            $totalNetoCombustible += $importeNeto;

            $productos[] = [
                'producto_id' => $productoId,
                'codigo' => $catalogo->codigo ?? (string) $productoId,
                'nombre' => $catalogo->nombre ?? 'Producto',
                'galones_despachados' => $galonesDespachados,
                'galones_credito' => $galonesCredito,
                'galones_descuento' => $galonesDescuento,
                'galones_vendidos' => $galonesVendidos,
                'precio_vigente' => $precioVigente,
                'importe_venta_normal' => $importeVentaNormal,
                'importe_descuentos' => $importeDescuentos,
                'importe_creditos' => $importeCreditos,
                'importe_neto' => $importeNeto,
            ];
        }

        $financiero = DB::table('resumen_financiero_turno')
            ->where('turno_operativo_id', $turnoId)
            ->first();

        $otrosIngresos = round((float) ($financiero->otros_ingresos ?? 0), 2);
        $ventasLubricantes = round((float) ($financiero->ventas_lubricantes ?? 0), 2);

        $gastos = 0.0;
        if (Esquema::existeTabla('gastos')) {
            $colMontoGasto = Esquema::resolver('gastos', ['monto','importe','costo','total']);
            $colTurnoGasto = Esquema::resolver('gastos', ['turno_operativo_id']);
            if ($colMontoGasto && $colTurnoGasto) {
                $gastos = round((float) DB::table('gastos')
                    ->where($colTurnoGasto, $turnoId)
                    ->sum($colMontoGasto), 2);
            }
        }

        $totalVentaNormal = round($totalVentaNormal, 2);
        $totalDescuentos = round($totalDescuentos, 2);
        $totalCreditos = round($totalCreditos, 2);
        $totalNetoCombustible = round($totalNetoCombustible, 2);
        $montoAEntregar = round(
            $totalNetoCombustible + $otrosIngresos + $ventasLubricantes - $gastos,
            2
        );

        return [
            'turno_id' => $turnoId,
            'productos' => $productos,
            'venta_normal_combustible' => $totalVentaNormal,
            'importe_descuentos' => $totalDescuentos,
            'importe_creditos' => $totalCreditos,
            'monto_neto_combustible' => $totalNetoCombustible,
            'otros_ingresos' => $otrosIngresos,
            'ventas_lubricantes' => $ventasLubricantes,
            'gastos' => $gastos,
            'monto_a_entregar' => $montoAEntregar,
            'inconsistencias' => $inconsistencias,
        ];
    }

    public function sincronizar(int $turnoId, bool $estricto = true): array
    {
        $calculo = $this->calcular($turnoId);

        if ($estricto && $calculo['inconsistencias']) {
            throw ValidationException::withMessages([
                'calculo' => $calculo['inconsistencias'][0],
            ]);
        }

        DB::transaction(function () use ($turnoId, $calculo) {
            if (Esquema::existeTabla('totales_producto_turno')) {
                DB::table('totales_producto_turno')
                    ->where('turno_operativo_id', $turnoId)
                    ->delete();

                foreach ($calculo['productos'] as $producto) {
                    $datos = Esquema::filtrar('totales_producto_turno', [
                        'turno_operativo_id' => $turnoId,
                        'producto_id' => $producto['producto_id'],
                        // "galones" conserva el despacho físico total del surtidor.
                        'galones' => $producto['galones_despachados'],
                        'galones_credito' => $producto['galones_credito'],
                        'galones_descuento' => $producto['galones_descuento'],
                        'galones_vendidos' => $producto['galones_vendidos'],
                        'precio_vigente' => $producto['precio_vigente'],
                        // importe_bruto pasa a representar la venta normal, excluyendo crédito/descuento.
                        'importe_bruto' => $producto['importe_venta_normal'],
                        'importe_creditos' => $producto['importe_creditos'],
                        'importe_descuentos' => $producto['importe_descuentos'],
                        'importe_neto' => $producto['importe_neto'],
                    ]);
                    DB::table('totales_producto_turno')->insert($datos);
                }
            }

            $datosFinancieros = Esquema::filtrar('resumen_financiero_turno', [
                'ventas_brutas_combustible' => $calculo['venta_normal_combustible'],
                'importe_descuentos' => $calculo['importe_descuentos'],
                'importe_creditos' => $calculo['importe_creditos'],
                'monto_neto_combustible' => $calculo['monto_neto_combustible'],
                'gastos_efectivo' => $calculo['gastos'],
                // neto_esperado = monto a entregar.
                'neto_esperado' => $calculo['monto_a_entregar'],
            ]);

            $existe = DB::table('resumen_financiero_turno')
                ->where('turno_operativo_id', $turnoId)
                ->exists();

            if ($existe) {
                DB::table('resumen_financiero_turno')
                    ->where('turno_operativo_id', $turnoId)
                    ->update($datosFinancieros);
            } else {
                DB::table('resumen_financiero_turno')->insert(Esquema::filtrar('resumen_financiero_turno', array_merge([
                    'turno_operativo_id' => $turnoId,
                    'otros_ingresos' => 0,
                    'ventas_lubricantes' => 0,
                    'efectivo_entregado' => 0,
                    'digital_operador' => 0,
                    'estado_validacion_digital' => 'PENDIENTE',
                    'hoja_origen' => 'SISTEMA',
                ], $datosFinancieros)));
            }
        });

        return $calculo;
    }
}
