<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ControladorModulo extends Controller
{
    private const MODULOS = [
        'movimientos' => [
            'titulo' => 'Movimientos',
            'descripcion' => 'Registro y consulta de movimientos operativos del grifo.',
            'icono' => 'movimientos',
        ],
        'turnos' => [
            'titulo' => 'Turnos',
            'descripcion' => 'Apertura, seguimiento y cierre de turnos operativos.',
            'icono' => 'turnos',
        ],
        'compras' => [
            'titulo' => 'Compras',
            'descripcion' => 'Compras de combustible, recepción de cisternas y proveedores.',
            'icono' => 'compras',
        ],
        'creditos' => [
            'titulo' => 'Créditos',
            'descripcion' => 'Ventas a crédito, saldos, pagos y seguimiento de clientes.',
            'icono' => 'creditos',
        ],
        'gastos' => [
            'titulo' => 'Gastos',
            'descripcion' => 'Control de egresos y categorías de gasto.',
            'icono' => 'gastos',
        ],
        'tanques' => [
            'titulo' => 'Tanques',
            'descripcion' => 'Control diario de existencias y conciliación de combustible.',
            'icono' => 'tanques',
        ],
        'trabajadores' => [
            'titulo' => 'Trabajadores',
            'descripcion' => 'Personal activo, histórico y asignación de cuentas de usuario.',
            'icono' => 'trabajadores',
        ],
        'clientes' => [
            'titulo' => 'Clientes',
            'descripcion' => 'Clientes y cuentas asociadas a ventas al crédito.',
            'icono' => 'clientes',
        ],
        'precios' => [
            'titulo' => 'Precios',
            'descripcion' => 'Historial y vigencia de precios por turno operativo.',
            'icono' => 'precios',
        ],
        'competencia' => [
            'titulo' => 'Competencia',
            'descripcion' => 'Seguimiento de precios de grifos competidores.',
            'icono' => 'competencia',
        ],
        'reportes' => [
            'titulo' => 'Reportes',
            'descripcion' => 'Reportes diarios, mensuales, anuales y comparativos.',
            'icono' => 'reportes',
        ],
        'configuracion' => [
            'titulo' => 'Configuración',
            'descripcion' => 'Parámetros generales, productos, surtidores y medios de pago.',
            'icono' => 'configuracion',
        ],
        'auditoria' => [
            'titulo' => 'Auditoría',
            'descripcion' => 'Trazabilidad de accesos y modificaciones realizadas en el sistema.',
            'icono' => 'auditoria',
        ],
    ];

    public function mostrar(Request $request, string $modulo)
    {
        abort_unless(Arr::has(self::MODULOS, $modulo), 404);

        $configuracion = self::MODULOS[$modulo];

        return view('modulos.proximamente', [
            'modulo' => $modulo,
            'titulo' => $configuracion['titulo'],
            'descripcion' => $configuracion['descripcion'],
            'icono' => $configuracion['icono'],
        ]);
    }
}
