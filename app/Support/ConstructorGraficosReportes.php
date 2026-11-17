<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Datos de visualización derivados EXCLUSIVAMENTE del detalle que devuelve el
 * controlador de reportes. No consulta ni modifica la base de datos.
 * La selección de «Todos» se realiza en el navegador sin recargar el reporte.
 */
final class ConstructorGraficosReportes
{
    public static function crear(string $tipo, Collection $filas): array
    {
        $config = self::configuraciones()[$tipo] ?? null;
        if ($config === null) {
            return ['tipo' => $tipo, 'dimensiones' => [], 'metricas' => [], 'registros' => []];
        }

        // Evita ofrecer magnitudes que el SQL real no devolvió.
        $primeras = $filas->first();
        $claves = $primeras === null ? [] : array_keys((array) $primeras);
        $metricas = array_values(array_filter($config['metricas'], static function (array $metrica) use ($claves, $primeras): bool {
            return $primeras === null || ($metrica['modo'] === 'conteo') || in_array($metrica['campo'], $claves, true);
        }));
        $dimensiones = array_values(array_filter($config['dimensiones'], static function (array $dimension) use ($claves, $primeras): bool {
            return $primeras === null || in_array($dimension['campo'], $claves, true);
        }));

        $registros = [];
        foreach ($filas as $fila) {
            $fecha = data_get($fila, $config['fecha']);
            $fecha = $fecha === null ? '' : substr((string) $fecha, 0, 10);
            if ($config['temporal'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                continue;
            }
            $r = ['fecha' => $fecha];
            foreach ($dimensiones as $d) {
                $valor = trim((string) (data_get($fila, $d['campo']) ?? ''));
                $r[$d['campo']] = $valor !== '' ? mb_substr($valor, 0, 110) : 'Sin especificar';
            }
            foreach ($metricas as $m) {
                $valor = $m['modo'] === 'conteo' ? 1 : data_get($fila, $m['campo']);
                $r[$m['campo']] = is_numeric($valor) && is_finite((float) $valor) ? (float) $valor : null;
            }
            $registros[] = $r;
        }

        return [
            'tipo' => $tipo,
            'temporal' => $config['temporal'],
            'compararPorDefecto' => $config['compararPorDefecto'] ?? '',
            'dimensiones' => $dimensiones,
            'metricas' => $metricas,
            'registros' => $registros,
            'nota' => $config['nota'] ?? '',
        ];
    }

    private static function configuraciones(): array
    {
        $d = static fn(string $campo, string $nombre): array => ['campo' => $campo, 'nombre' => $nombre];
        $m = static fn(string $campo, string $nombre, string $unidad, string $modo = 'suma'): array => [
            'campo' => $campo, 'nombre' => $nombre, 'unidad' => $unidad, 'modo' => $modo,
        ];

        return [
            'ventas' => [
                'fecha' => 'fecha_operacion', 'temporal' => true,
                'dimensiones' => [$d('turno', 'Turno'), $d('trabajador', 'Trabajador')],
                'metricas' => [$m('venta_combustible', 'Venta de combustibles', 'S/'), $m('otros_ingresos', 'Otros ingresos', 'S/'), $m('lubricantes', 'Lubricantes', 'S/')],
                'nota' => 'Los ingresos se presentan por separado para evitar sumarlos dos veces con los importes netos del turno.',
            ],
            'galones' => [
                'fecha' => 'fecha_operacion', 'temporal' => true,
                'dimensiones' => [$d('producto', 'Combustible'), $d('turno', 'Turno'), $d('trabajador', 'Trabajador')],
                'metricas' => [$m('galones', 'Galones despachados', 'gal')],
            ],
            'caja' => [
                'fecha' => 'fecha_control', 'temporal' => true,
                'dimensiones' => [],
                'metricas' => [$m('efectivo_esperado', 'Efectivo esperado', 'S/', 'ultimo'), $m('efectivo_contado', 'Efectivo contado', 'S/', 'ultimo'), $m('diferencia', 'Diferencia de caja', 'S/', 'ultimo')],
                'nota' => 'Los saldos de caja muestran el último control disponible de cada intervalo; no se suman saldos de días distintos.',
            ],
            'creditos' => [
                'fecha' => 'fecha', 'temporal' => true,
                'dimensiones' => [$d('cliente', 'Cliente'), $d('turno', 'Turno')],
                'metricas' => [$m('operaciones', 'Operaciones a crédito', 'operaciones', 'conteo'), $m('importe', 'Importe de créditos', 'S/')],
                'nota' => 'El importe representa las ventas a crédito registradas, no necesariamente el saldo pendiente de cobro.',
            ],
            'descuentos' => [
                'fecha' => 'fecha', 'temporal' => true,
                'dimensiones' => [$d('producto', 'Combustible'), $d('turno', 'Turno')],
                'metricas' => [$m('galones', 'Galones con descuento', 'gal'), $m('importe', 'Importe cobrado con descuento', 'S/')],
                'nota' => 'El importe cobrado no equivale al ahorro concedido: este último requeriría comparar con el precio de venta sin descuento.',
            ],
            'gastos' => [
                'fecha' => 'fecha', 'temporal' => true,
                'dimensiones' => [$d('categoria', 'Categoría'), $d('turno', 'Turno')],
                'metricas' => [$m('monto', 'Monto de gastos', 'S/')],
            ],
            'compras' => [
                'fecha' => 'fecha', 'temporal' => true,
                'dimensiones' => [$d('producto', 'Combustible')],
                'metricas' => [$m('galones', 'Galones comprados', 'gal'), $m('monto', 'Importe de compras', 'S/')],
            ],
            'tanques' => [
                'fecha' => 'fecha', 'temporal' => true,
                'dimensiones' => [$d('producto', 'Combustible')],
                'metricas' => [$m('saldo_teorico', 'Saldo teórico', 'gal', 'ultimo'), $m('saldo_medido', 'Saldo medido', 'gal', 'ultimo'), $m('diferencia', 'Diferencia', 'gal', 'ultimo')],
                'nota' => 'En períodos semanales y mensuales se muestra la última medición disponible, no la suma de existencias.',
            ],
            'conciliacion' => [
                'fecha' => 'fecha', 'temporal' => true,
                'dimensiones' => [$d('producto', 'Combustible')],
                'metricas' => [$m('saldo_teorico', 'Saldo teórico', 'gal', 'ultimo'), $m('saldo_medido', 'Saldo medido', 'gal', 'ultimo'), $m('diferencia', 'Diferencia', 'gal', 'ultimo')],
                'nota' => 'Los saldos corresponden a mediciones; los agrupamientos de varios días utilizan el último día con registros.',
            ],
            'precios' => [
                'fecha' => 'vigente_desde', 'temporal' => true,
                'dimensiones' => [$d('producto', 'Producto')],
                'compararPorDefecto' => 'producto',
                'metricas' => [$m('precio', 'Precio registrado', 'S/ por gal', 'ultimo')],
                'nota' => 'Cada punto representa un cambio registrado, no necesariamente todos los días de vigencia del precio.',
            ],
            'competencia' => [
                'fecha' => 'fecha', 'temporal' => true,
                'dimensiones' => [$d('producto', 'Producto'), $d('grifo', 'Grifo competidor')],
                'compararPorDefecto' => 'grifo',
                'metricas' => [$m('precio', 'Precio observado', 'S/ por gal', 'ultimo')],
                'nota' => 'Para comparar precios homogéneos, selecciona un producto; «Todos» conserva la visión general pero puede mezclar productos.',
            ],
            'trabajadores' => [
                'fecha' => '', 'temporal' => false,
                'dimensiones' => [$d('trabajador', 'Trabajador')],
                'compararPorDefecto' => 'trabajador',
                'metricas' => [$m('turnos', 'Turnos atendidos', 'turnos'), $m('galones', 'Galones atendidos', 'gal'), $m('ventas', 'Ventas asociadas', 'S/')],
                'nota' => 'Comparación descriptiva. Los resultados deben interpretarse considerando la cantidad de turnos asignados a cada trabajador.',
            ],
            'extraordinarios' => [
                'fecha' => 'fecha_operacion', 'temporal' => true,
                'dimensiones' => [$d('turno', 'Tipo de corte'), $d('trabajador', 'Trabajador')],
                'metricas' => [$m('cortes', 'Cortes extraordinarios', 'cortes', 'conteo')],
            ],
        ];
    }
}
