<?php

namespace App\Http\Controllers;

use App\Support\AccesoGastosSupervisor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ControladorReportesMejorados extends ControladorBaseGrifo
{
    private const TIPOS = [
        'ventas', 'galones', 'caja', 'creditos', 'descuentos', 'gastos', 'compras',
        'tanques', 'precios', 'competencia', 'trabajadores', 'extraordinarios', 'conciliacion',
    ];

    public function indice(Request $request)
    {
        $this->autorizarReportes();
        [$desde, $hasta] = $this->rango($request);

        $galonesPorProducto = $this->resumenGalones($desde, $hasta);
        $metricas = [
            'ventas' => $this->totalVentas($desde, $hasta),
            'galones' => (float) $galonesPorProducto->sum('galones'),
            'caja' => $this->contarRango('controles_diarios_caja', ['fecha_control','fecha'], $desde, $hasta),
            'creditos' => $this->contarRango('ventas_credito', ['fecha_venta','fecha'], $desde, $hasta),
            'descuentos' => $this->contarRango('descuentos', ['fecha','fecha_descuento','creado_en'], $desde, $hasta),
            'gastos' => $this->sumarRango('gastos', ['fecha_gasto','fecha'], ['monto','importe','total'], $desde, $hasta),
            'compras' => $this->contarRango('compras_combustible', ['fecha_compra','fecha'], $desde, $hasta),
            'tanques' => $this->contarRango('controles_diarios_tanque', ['fecha_control','fecha'], $desde, $hasta),
            'precios' => $this->contarRango('historial_precios', ['vigente_desde','fecha_vigencia','fecha'], $desde, $hasta),
            'competencia' => $this->contarRango('precios_competencia', ['fecha_registro','fecha'], $desde, $hasta),
            'trabajadores' => $this->contarRango('turnos_operativos', ['fecha_operacion'], $desde, $hasta),
            'extraordinarios' => $this->contarExtraordinarios($desde, $hasta),
            'conciliacion' => $this->contarRango('controles_diarios_tanque', ['fecha_control','fecha'], $desde, $hasta),
        ];

        $reportes = collect([
            ['clave'=>'ventas','titulo'=>'Ventas','descripcion'=>'Ventas por día, turno y trabajador.','valor'=>'S/ '.number_format($metricas['ventas'],2)],
            ['clave'=>'galones','titulo'=>'Galones despachados','descripcion'=>'Desagregado por combustible, día y turno.','valor'=>number_format($metricas['galones'],3).' gal'],
            ['clave'=>'caja','titulo'=>'Control de caja','descripcion'=>'Controles diarios y diferencias registradas.','valor'=>number_format($metricas['caja'])],
            ['clave'=>'creditos','titulo'=>'Créditos','descripcion'=>'Créditos por cliente, turno y fecha.','valor'=>number_format($metricas['creditos'])],
            ['clave'=>'descuentos','titulo'=>'Descuentos','descripcion'=>'Despachos con descuento por producto y turno.','valor'=>number_format($metricas['descuentos'])],
            ['clave'=>'gastos','titulo'=>'Gastos','descripcion'=>'Gastos por fecha, categoría y turno.','valor'=>'S/ '.number_format($metricas['gastos'],2)],
            ['clave'=>'compras','titulo'=>'Compras de combustible','descripcion'=>'Ingresos de combustible por producto.','valor'=>number_format($metricas['compras'])],
            ['clave'=>'tanques','titulo'=>'Control de tanques','descripcion'=>'Mediciones físicas y saldos teóricos.','valor'=>number_format($metricas['tanques'])],
            ['clave'=>'precios','titulo'=>'Historial de precios','descripcion'=>'Cambios de precio y vigencia por producto.','valor'=>number_format($metricas['precios'])],
            ['clave'=>'competencia','titulo'=>'Precios de competencia','descripcion'=>'Registros de precios de grifos competidores.','valor'=>number_format($metricas['competencia'])],
            ['clave'=>'trabajadores','titulo'=>'Operación por trabajador','descripcion'=>'Turnos, ventas y galones atendidos.','valor'=>number_format($metricas['trabajadores']).' turnos'],
            ['clave'=>'extraordinarios','titulo'=>'Cortes extraordinarios','descripcion'=>'T4/T5/T6 y otros cortes fuera de la secuencia regular.','valor'=>number_format($metricas['extraordinarios'])],
            ['clave'=>'conciliacion','titulo'=>'Conciliación de combustible','descripcion'=>'Saldo teórico, saldo medido y diferencia por tanque.','valor'=>number_format($metricas['conciliacion'])],
        ]);

        return view('reportes.mejorados.indice', compact('desde','hasta','reportes','galonesPorProducto'));
    }

    public function detalle(Request $request, string $tipo)
    {
        $this->autorizarReportes();
        abort_unless(in_array($tipo, self::TIPOS, true), 404);
        [$desde, $hasta] = $this->rango($request);

        $metodo = 'detalle'.str_replace(' ', '', ucwords(str_replace('_', ' ', $tipo)));
        $resultado = method_exists($this, $metodo)
            ? $this->{$metodo}($desde, $hasta)
            : ['titulo'=>ucfirst($tipo),'columnas'=>[],'filas'=>collect()];

        // La totalidad de los registros alimenta los gráficos; la tabla muestra
        // exclusivamente la página solicitada, sin alterar las sumas del período.
        $filasCompletas = $this->ordenarFilasRecientes(collect($resultado['filas'] ?? []), $tipo);
        $filasPorPaginaSolicitadas = $request->query('filas', 10);
        $filasPorPagina = is_scalar($filasPorPaginaSolicitadas)
            && in_array((string) $filasPorPaginaSolicitadas, ['10', '20', '50', '100'], true)
                ? (int) $filasPorPaginaSolicitadas : 10;
        $paginasTotales = max(1, (int) ceil($filasCompletas->count() / $filasPorPagina));
        $paginaSolicitada = $request->query('pagina', 1);
        $pagina = is_scalar($paginaSolicitada) && ctype_digit((string) $paginaSolicitada)
            ? max(1, min($paginasTotales, (int) $paginaSolicitada)) : 1;

        $resultado['configuracionGrafica'] = \App\Support\ConstructorGraficosReportes::crear($tipo, $filasCompletas);
        $resultado['tieneTurno'] = $filasCompletas->contains(fn($fila) => data_get($fila, 'turno_operativo_id'));
        $resultado['filas'] = new LengthAwarePaginator(
            $filasCompletas->slice(($pagina - 1) * $filasPorPagina, $filasPorPagina)->values(),
            $filasCompletas->count(), $filasPorPagina, $pagina,
            [
                'path' => $request->url(), 'pageName' => 'pagina',
                'query' => ['desde' => $desde, 'hasta' => $hasta, 'filas' => $filasPorPagina],
                'fragment' => 'detalle-registros',
            ]
        );
        $resultado['filasPorPagina'] = $filasPorPagina;
        $resultado['tipo'] = $tipo;
        $resultado['desde'] = $desde;
        $resultado['hasta'] = $hasta;
        $resultado['galonesPorProducto'] = $tipo === 'galones' ? $this->resumenGalones($desde, $hasta) : collect();

        return view('reportes.mejorados.detalle', $resultado);
    }

    private function detalleVentas(string $desde, string $hasta): array
    {
        if (! Schema::hasTable('turnos_operativos') || ! Schema::hasTable('resumen_financiero_turno')) {
            return $this->vacio('Ventas');
        }
        $q = DB::table('turnos_operativos as t')
            ->leftJoin('tipos_turno as tt','tt.id','=','t.tipo_turno_id')
            ->leftJoin('trabajadores as tr','tr.id','=','t.trabajador_id')
            ->leftJoin('resumen_financiero_turno as rf','rf.turno_operativo_id','=','t.id')
            ->where('t.establecimiento_id',$this->establecimientoId())
            ->whereBetween('t.fecha_operacion',[$desde,$hasta])
            ->orderByDesc('t.fecha_operacion')->orderByDesc('t.id');

        $filas = $q->get([
            't.id as turno_operativo_id','t.fecha_operacion',
            DB::raw("COALESCE(tt.codigo,'—') as turno"),
            DB::raw("COALESCE(tr.nombre_completo,'—') as trabajador"),
            DB::raw('COALESCE(rf.ventas_brutas_combustible,0) as venta_combustible'),
            DB::raw('COALESCE(rf.otros_ingresos,0) as otros_ingresos'),
            DB::raw('COALESCE(rf.ventas_lubricantes,0) as lubricantes'),
            DB::raw('COALESCE(rf.gastos_efectivo,0) as gastos'),
            DB::raw('COALESCE(rf.neto_esperado,0) as neto_esperado'),
        ]);

        return ['titulo'=>'Ventas','columnas'=>[
            'fecha_operacion'=>'Fecha','turno'=>'Turno','trabajador'=>'Trabajador','venta_combustible'=>'Combustible',
            'otros_ingresos'=>'Otros ingresos','lubricantes'=>'Lubricantes','gastos'=>'Gastos','neto_esperado'=>'Neto esperado'
        ],'filas'=>$filas,'moneda'=>['venta_combustible','otros_ingresos','lubricantes','gastos','neto_esperado']];
    }

    private function detalleGalones(string $desde, string $hasta): array
    {
        if (! Schema::hasTable('totales_producto_turno')) return $this->vacio('Galones despachados');
        $filas = DB::table('totales_producto_turno as tp')
            ->join('turnos_operativos as t','t.id','=','tp.turno_operativo_id')
            ->leftJoin('tipos_turno as tt','tt.id','=','t.tipo_turno_id')
            ->leftJoin('trabajadores as tr','tr.id','=','t.trabajador_id')
            ->join('productos as p','p.id','=','tp.producto_id')
            ->where('t.establecimiento_id',$this->establecimientoId())
            ->whereBetween('t.fecha_operacion',[$desde,$hasta])
            ->where('p.categoria','COMBUSTIBLE')
            ->orderByDesc('t.fecha_operacion')->orderByDesc('t.id')->orderBy('p.id')
            ->get([
                't.id as turno_operativo_id','t.fecha_operacion',
                DB::raw("COALESCE(tt.codigo,'—') as turno"),
                DB::raw("COALESCE(tr.nombre_completo,'—') as trabajador"),
                'p.codigo as codigo_producto','p.nombre as producto','tp.galones'
            ]);
        return ['titulo'=>'Galones despachados','columnas'=>[
            'fecha_operacion'=>'Fecha','turno'=>'Turno','trabajador'=>'Trabajador','codigo_producto'=>'Código','producto'=>'Combustible','galones'=>'Galones'
        ],'filas'=>$filas,'decimales'=>['galones'=>3]];
    }

    private function detalleCaja(string $desde, string $hasta): array
    {
        // Estructura real de controles_diarios_caja validada el 25/09/2026.
        // Se usan los nombres físicos actuales para que el detalle muestre
        // exactamente las mismas magnitudes que el módulo Control de caja.
        return $this->detalleTablaSimple('Control diario de caja','controles_diarios_caja',['fecha_control'],$desde,$hasta,[
            ['fecha_control'],
            ['efectivo_dia_anterior'],
            ['efectivo_total_dia'],
            ['otros_ingresos_caja'],
            ['digital_diario'],
            ['gastos_efectivo_dia'],
            ['compras_efectivo_dia'],
            ['depositos_banco'],
            ['efectivo_esperado', 'efectivo_libros'],
            ['efectivo_contado'],
            ['diferencia'],
            ['observaciones'],
        ]);
    }

    private function detalleCreditos(string $desde, string $hasta): array
    {
        if (! Schema::hasTable('ventas_credito')) {
            return $this->vacio('Créditos');
        }

        $fecha = $this->columna('ventas_credito', ['fecha_venta','fecha']);
        $colCliente = Schema::hasTable('clientes')
            ? $this->columna('clientes', ['nombre','razon_social','nombre_cliente'])
            : null;

        $q = DB::table('ventas_credito as v')
            ->leftJoin('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->leftJoin('turnos_operativos as t', 't.id', '=', 'v.turno_operativo_id')
            ->leftJoin('tipos_turno as tt', 'tt.id', '=', 't.tipo_turno_id')
            ->where('v.establecimiento_id', $this->establecimientoId());

        if ($fecha) {
            $q->whereDate("v.$fecha", '>=', $desde)->whereDate("v.$fecha", '<=', $hasta);
        }

        $importeDisponible = false;
        if (Schema::hasTable('detalle_ventas_credito')) {
            $cantidad = $this->columna('detalle_ventas_credito', ['cantidad_galones','cantidad_gal','galones','cantidad']);
            $precio = $this->columna('detalle_ventas_credito', ['precio_unitario','precio_credito','precio','precio_venta']);
            if ($cantidad && $precio) {
                $detalle = DB::table('detalle_ventas_credito as d')
                    ->select('d.venta_credito_id', DB::raw("SUM(COALESCE(d.`{$cantidad}`,0) * COALESCE(d.`{$precio}`,0)) as importe"))
                    ->groupBy('d.venta_credito_id');
                $q->leftJoinSub($detalle, 'dc', function ($join) {
                    $join->on('dc.venta_credito_id', '=', 'v.id');
                });
                $importeDisponible = true;
            }
        }

        $select = [
            'v.id',
            'v.turno_operativo_id',
            DB::raw("COALESCE(tt.codigo,'—') as turno"),
            DB::raw($colCliente ? "COALESCE(c.`{$colCliente}`,'—') as cliente" : "'—' as cliente"),
        ];
        if ($fecha) $select[] = "v.$fecha as fecha";
        if (Schema::hasColumn('ventas_credito','numero_vale')) $select[] = 'v.numero_vale';
        if (Schema::hasColumn('ventas_credito','observaciones')) $select[] = 'v.observaciones';
        if ($importeDisponible) $select[] = DB::raw('COALESCE(dc.importe,0) as importe');

        $filas = $q->orderByDesc('v.id')->get($select);
        $columnas = [
            'fecha'=>'Fecha','turno'=>'Turno','cliente'=>'Cliente','numero_vale'=>'Vale',
        ];
        if ($importeDisponible) $columnas['importe'] = 'Importe';
        $columnas['observaciones'] = 'Observaciones';

        return [
            'titulo'=>'Créditos',
            'columnas'=>$columnas,
            'filas'=>$filas,
            'moneda'=>$importeDisponible ? ['importe'] : [],
        ];
    }

    private function detalleDescuentos(string $desde, string $hasta): array
    {
        if (! Schema::hasTable('descuentos')) return $this->vacio('Descuentos');
        $fecha=$this->columna('descuentos',['fecha','fecha_descuento','creado_en']);
        $gal=$this->columna('descuentos',['galones','cantidad_galones','cantidad']);
        $precio=$this->columna('descuentos',['precio','precio_descuento']);
        $importe=$this->columna('descuentos',['importe','monto','total']);
        $q=DB::table('descuentos as d')->leftJoin('productos as p','p.id','=','d.producto_id')->leftJoin('turnos_operativos as t','t.id','=','d.turno_operativo_id')->leftJoin('tipos_turno as tt','tt.id','=','t.tipo_turno_id')->where('d.establecimiento_id',$this->establecimientoId());
        if($fecha)$q->whereDate("d.$fecha", '>=', $desde)->whereDate("d.$fecha", '<=', $hasta);
        $select=['d.id','d.turno_operativo_id','p.codigo as producto',DB::raw("COALESCE(tt.codigo,'—') as turno")];
        if($fecha)$select[]="d.$fecha as fecha"; if($gal)$select[]="d.$gal as galones"; if($precio)$select[]="d.$precio as precio"; if($importe)$select[]="d.$importe as importe";
        $filas=$q->orderByDesc('d.id')->get($select);
        return ['titulo'=>'Descuentos','columnas'=>['fecha'=>'Fecha','turno'=>'Turno','producto'=>'Producto','galones'=>'Galones','precio'=>'Precio','importe'=>'Importe'],'filas'=>$filas,'decimales'=>['galones'=>3],'moneda'=>['precio','importe']];
    }

    private function detalleGastos(string $desde,string $hasta): array
    {
        if(!Schema::hasTable('gastos')) return $this->vacio('Gastos');
        $fecha=$this->columna('gastos',['fecha_gasto','fecha']); $monto=$this->columna('gastos',['monto','importe','total']); $detalle=$this->columna('gastos',['detalle','descripcion','concepto']);
        $q=DB::table('gastos as g')->leftJoin('categorias_gasto as cg','cg.id','=','g.categoria_id')->leftJoin('turnos_operativos as t','t.id','=','g.turno_operativo_id')->leftJoin('tipos_turno as tt','tt.id','=','t.tipo_turno_id')->where('g.establecimiento_id',$this->establecimientoId());
        AccesoGastosSupervisor::filtrar($q, 'g');
        if($fecha)$q->whereDate("g.$fecha", '>=', $desde)->whereDate("g.$fecha", '<=', $hasta);
        $select=['g.id','g.turno_operativo_id',DB::raw("COALESCE(cg.nombre,'—') as categoria"),DB::raw("COALESCE(tt.codigo,'—') as turno")];
        if($fecha)$select[]="g.$fecha as fecha";if($detalle)$select[]="g.$detalle as detalle";if($monto)$select[]="g.$monto as monto";
        return ['titulo'=>'Gastos','columnas'=>['fecha'=>'Fecha','turno'=>'Turno','categoria'=>'Categoría','detalle'=>'Detalle','monto'=>'Monto'],'filas'=>$q->orderByDesc('g.id')->get($select),'moneda'=>['monto']];
    }

    private function detalleCompras(string $desde,string $hasta): array
    {
        if(!Schema::hasTable('compras_combustible')) return $this->vacio('Compras de combustible');
        $fecha = $this->columna('compras_combustible', ['fecha_compra', 'fecha']);
        $galones = $this->columna('compras_combustible', ['galones', 'cantidad_galones', 'cantidad']);
        $q = DB::table('compras_combustible as c')
            ->leftJoin('productos as p', 'p.id', '=', 'c.producto_id')
            ->where('c.establecimiento_id', $this->establecimientoId());
        $select = ['c.id', 'p.codigo as codigo_producto', 'p.nombre as producto'];
        $columnas = ['fecha' => 'Fecha', 'codigo_producto' => 'Código', 'producto' => 'Combustible', 'galones' => 'Galones'];
        if ($fecha) {
            $q->whereDate("c.$fecha", '>=', $desde)->whereDate("c.$fecha", '<=', $hasta);
            $select[] = "c.$fecha as fecha";
        }
        if ($galones) {
            $select[] = "c.$galones as galones";
        }
        if (auth()->user()->rol === 'ADMINISTRADOR'
            && ($costo = $this->columna('compras_combustible', ['costo_total', 'monto_total', 'importe_total', 'total', 'monto']))) {
            $select[] = "c.$costo as monto";
            $columnas['monto'] = 'Costo total';
        }

        return [
            'titulo' => 'Compras de combustible',
            'columnas' => $columnas,
            'filas' => $q->orderByDesc('c.id')->get($select),
            'decimales' => ['galones' => 3],
            'moneda' => array_intersect(['monto'], array_keys($columnas)),
        ];
    }

    private function detalleTanques(string $desde,string $hasta): array
    {
        return $this->detalleControlesTanque($desde,$hasta,'Control de tanques');
    }

    private function detalleConciliacion(string $desde,string $hasta): array
    {
        return $this->detalleControlesTanque($desde,$hasta,'Conciliación de combustible');
    }

    private function detalleControlesTanque(string $desde,string $hasta,string $titulo): array
    {
        if(!Schema::hasTable('controles_diarios_tanque')) return $this->vacio($titulo);
        $fecha=$this->columna('controles_diarios_tanque',['fecha_control','fecha']);
        $teo=$this->columna('controles_diarios_tanque',['saldo_teorico_galones','saldo_teorico_gal','saldo_teorico']);
        $med=$this->columna('controles_diarios_tanque',['saldo_medido_galones','saldo_medido_gal','saldo_medido']);
        $dif=$this->columna('controles_diarios_tanque',['diferencia_galones','diferencia_gal','diferencia']);
        $q=DB::table('controles_diarios_tanque as c')->join('tanques as ta','ta.id','=','c.tanque_id')->join('productos as p','p.id','=','ta.producto_id')->where('ta.establecimiento_id',$this->establecimientoId());if($fecha)$q->whereDate("c.$fecha", '>=', $desde)->whereDate("c.$fecha", '<=', $hasta);
        $select=['c.id','p.codigo as codigo_producto','p.nombre as producto'];if($fecha)$select[]="c.$fecha as fecha";if($teo)$select[]="c.$teo as saldo_teorico";if($med)$select[]="c.$med as saldo_medido";if($dif)$select[]="c.$dif as diferencia";
        return ['titulo'=>$titulo,'columnas'=>['fecha'=>'Fecha','codigo_producto'=>'Código','producto'=>'Combustible','saldo_teorico'=>'Saldo teórico','saldo_medido'=>'Saldo medido','diferencia'=>'Diferencia'],'filas'=>$q->orderByDesc('c.id')->get($select),'decimales'=>['saldo_teorico'=>3,'saldo_medido'=>3,'diferencia'=>3]];
    }

    private function detallePrecios(string $desde,string $hasta): array
    {
        if(!Schema::hasTable('historial_precios')) return $this->vacio('Historial de precios');
        $fecha=$this->columna('historial_precios',['vigente_desde','fecha_vigencia','fecha']);$precio=$this->columna('historial_precios',['precio','precio_venta']);
        $q=DB::table('historial_precios as h')->join('productos as p','p.id','=','h.producto_id')->where('h.establecimiento_id',$this->establecimientoId());if($fecha)$q->whereBetween(DB::raw("DATE(h.$fecha)"),[$desde,$hasta]);
        $select=['h.id','p.codigo as codigo_producto','p.nombre as producto'];if($fecha)$select[]="h.$fecha as vigente_desde";if($precio)$select[]="h.$precio as precio";
        return ['titulo'=>'Historial de precios','columnas'=>['vigente_desde'=>'Vigente desde','codigo_producto'=>'Código','producto'=>'Producto','precio'=>'Precio'],'filas'=>$q->orderByDesc('h.id')->get($select),'moneda'=>['precio']];
    }

    private function detalleCompetencia(string $desde,string $hasta): array
    {
        if(!Schema::hasTable('precios_competencia')) return $this->vacio('Precios de competencia');
        $fecha=$this->columna('precios_competencia',['fecha_registro','fecha']);$precio=$this->columna('precios_competencia',['precio']);
        $q=DB::table('precios_competencia as pc')
            ->leftJoin('grifos_competidores as g','g.id','=','pc.grifo_competidor_id')
            ->leftJoin('productos as p','p.id','=','pc.producto_id');
        if (Schema::hasColumn('grifos_competidores','establecimiento_id')) {
            $q->where('g.establecimiento_id', $this->establecimientoId());
        }
        if($fecha)$q->whereDate("pc.$fecha", '>=', $desde)->whereDate("pc.$fecha", '<=', $hasta);
        $select=['pc.id',DB::raw("COALESCE(g.nombre,'—') as grifo"),'p.codigo as producto'];if($fecha)$select[]="pc.$fecha as fecha";if($precio)$select[]="pc.$precio as precio";
        return ['titulo'=>'Precios de competencia','columnas'=>['fecha'=>'Fecha','grifo'=>'Grifo','producto'=>'Producto','precio'=>'Precio'],'filas'=>$q->orderByDesc('pc.id')->get($select),'moneda'=>['precio']];
    }

    private function detalleTrabajadores(string $desde, string $hasta): array
    {
        $galonesPorTurno = DB::table('totales_producto_turno')
            ->select('turno_operativo_id', DB::raw('SUM(galones) as galones'))
            ->groupBy('turno_operativo_id');

        $filas = DB::table('turnos_operativos as t')
            ->leftJoin('trabajadores as tr', 'tr.id', '=', 't.trabajador_id')
            ->leftJoin('resumen_financiero_turno as rf', 'rf.turno_operativo_id', '=', 't.id')
            ->leftJoinSub($galonesPorTurno, 'gt', function ($join) {
                $join->on('gt.turno_operativo_id', '=', 't.id');
            })
            ->where('t.establecimiento_id', $this->establecimientoId())
            ->whereBetween('t.fecha_operacion', [$desde, $hasta])
            ->groupBy('tr.id', 'tr.nombre_completo')
            ->orderByRaw('MAX(t.fecha_operacion) DESC')
            ->orderBy('tr.nombre_completo')
            ->get([
                DB::raw('MAX(t.fecha_operacion) as ultima_fecha_operacion'),
                DB::raw("COALESCE(tr.nombre_completo,'Sin trabajador') as trabajador"),
                DB::raw('COUNT(t.id) as turnos'),
                DB::raw('SUM(COALESCE(gt.galones,0)) as galones'),
                DB::raw('SUM(COALESCE(rf.ventas_brutas_combustible,0)+COALESCE(rf.otros_ingresos,0)+COALESCE(rf.ventas_lubricantes,0)) as ventas'),
            ]);

        return [
            'titulo' => 'Actividad por trabajador',
            'descripcion' => 'Resumen descriptivo de turnos, galones y ventas asociados a cada trabajador.',
            'columnas' => [
                'ultima_fecha_operacion' => 'Última actividad',
                'trabajador' => 'Trabajador',
                'turnos' => 'Turnos',
                'galones' => 'Galones',
                'ventas' => 'Ventas',
            ],
            'decimales' => ['galones' => 3],
            'moneda' => ['ventas'],
            'filas' => $filas,
        ];
    }

    private function detalleExtraordinarios(string $desde,string $hasta): array
    {
        if(!Schema::hasTable('turnos_operativos')) return $this->vacio('Cortes extraordinarios');
        $filas=DB::table('turnos_operativos as t')->join('tipos_turno as tt','tt.id','=','t.tipo_turno_id')->leftJoin('trabajadores as tr','tr.id','=','t.trabajador_id')
            ->where('t.establecimiento_id',$this->establecimientoId())->whereBetween('t.fecha_operacion',[$desde,$hasta])->whereIn('tt.codigo',['T4','T5','T6'])
            ->orderByDesc('t.fecha_operacion')->orderByDesc('t.id')->get(['t.id as turno_operativo_id','t.fecha_operacion','tt.codigo as turno','tt.nombre as tipo','tr.nombre_completo as trabajador','t.observaciones']);
        return ['titulo'=>'Cortes extraordinarios','columnas'=>['fecha_operacion'=>'Fecha','turno'=>'Corte','tipo'=>'Tipo','trabajador'=>'Trabajador','observaciones'=>'Observaciones'],'filas'=>$filas];
    }

    /**
     * Orden cronológico del detalle con desempate por identificador.
     * La fila con la fecha de operación más reciente siempre aparece primero,
     * incluso cuando un registro antiguo se haya ingresado posteriormente.
     */
    private function ordenarFilasRecientes(Collection $filas, string $tipo): Collection
    {
        $campoFecha = match ($tipo) {
            'ventas', 'galones', 'extraordinarios' => 'fecha_operacion',
            'trabajadores' => 'ultima_fecha_operacion',
            'caja' => 'fecha_control',
            'precios' => 'vigente_desde',
            default => 'fecha',
        };

        return $filas->sort(static function ($a, $b) use ($campoFecha): int {
            $fechaA = trim((string) (data_get($a, $campoFecha) ?? ''));
            $fechaB = trim((string) (data_get($b, $campoFecha) ?? ''));
            $porFecha = strcmp($fechaB, $fechaA);
            if ($porFecha !== 0) return $porFecha;

            $idA = (int) (data_get($a, 'id') ?? data_get($a, 'turno_operativo_id') ?? 0);
            $idB = (int) (data_get($b, 'id') ?? data_get($b, 'turno_operativo_id') ?? 0);
            if ($idA !== $idB) return $idB <=> $idA;

            return strcmp(
                (string) (data_get($a, 'trabajador') ?? data_get($a, 'producto') ?? ''),
                (string) (data_get($b, 'trabajador') ?? data_get($b, 'producto') ?? '')
            );
        })->values();
    }

    private function resumenGalones(string $desde,string $hasta)
    {
        if(!Schema::hasTable('totales_producto_turno')) return collect();
        return DB::table('totales_producto_turno as tp')->join('turnos_operativos as t','t.id','=','tp.turno_operativo_id')->join('productos as p','p.id','=','tp.producto_id')
            ->where('t.establecimiento_id',$this->establecimientoId())->whereBetween('t.fecha_operacion',[$desde,$hasta])->where('p.categoria','COMBUSTIBLE')
            ->groupBy('p.id','p.codigo','p.nombre')->orderBy('p.id')->get(['p.codigo','p.nombre',DB::raw('SUM(COALESCE(tp.galones,0)) as galones')]);
    }

    private function totalVentas(string $desde,string $hasta): float
    {
        if(!Schema::hasTable('resumen_financiero_turno')) return 0.0;
        return (float) (DB::table('resumen_financiero_turno as rf')
            ->join('turnos_operativos as t','t.id','=','rf.turno_operativo_id')
            ->where('t.establecimiento_id',$this->establecimientoId())
            ->whereBetween('t.fecha_operacion',[$desde,$hasta])
            ->selectRaw('SUM(COALESCE(rf.ventas_brutas_combustible,0)+COALESCE(rf.otros_ingresos,0)+COALESCE(rf.ventas_lubricantes,0)) as total')
            ->value('total') ?? 0);
    }

    private function contarExtraordinarios(string $desde,string $hasta): int
    {
        if(!Schema::hasTable('turnos_operativos')||!Schema::hasTable('tipos_turno'))return 0;
        return DB::table('turnos_operativos as t')->join('tipos_turno as tt','tt.id','=','t.tipo_turno_id')->where('t.establecimiento_id',$this->establecimientoId())->whereBetween('t.fecha_operacion',[$desde,$hasta])->whereIn('tt.codigo',['T4','T5','T6'])->count();
    }

    private function detalleTablaSimple(string $titulo,string $tabla,array $fechas,string $desde,string $hasta,array $grupos): array
    {
        if(!Schema::hasTable($tabla))return $this->vacio($titulo);$fecha=$this->columna($tabla,$fechas);$q=DB::table($tabla)->where('establecimiento_id',$this->establecimientoId());if($fecha)$q->whereDate($fecha, '>=', $desde)->whereDate($fecha, '<=', $hasta);
        $select=['id'];$columnas=[];$moneda=[];$dec=[];
        foreach($grupos as $grupo){$col=$this->columna($tabla,$grupo);if(!$col)continue;$alias=$grupo[0];$select[]="$col as $alias";$columnas[$alias]=$this->etiqueta($alias);if(str_contains($alias,'efectivo')||str_contains($alias,'digital')||str_contains($alias,'gasto')||str_contains($alias,'compra')||str_contains($alias,'deposito')||$alias==='diferencia')$moneda[]=$alias;}
        return ['titulo'=>$titulo,'columnas'=>$columnas,'filas'=>$q->orderByDesc($fecha?:'id')->orderByDesc('id')->get($select),'moneda'=>$moneda,'decimales'=>$dec];
    }

    private function contarRango(string $tabla,array $fechas,string $desde,string $hasta): int
    {
        if(!Schema::hasTable($tabla))return 0;$fecha=$this->columna($tabla,$fechas);$q=DB::table($tabla);if(Schema::hasColumn($tabla,'establecimiento_id'))$q->where('establecimiento_id',$this->establecimientoId());if($fecha)$q->whereBetween(DB::raw("DATE($fecha)"),[$desde,$hasta]);return (int)$q->count();
    }

    private function sumarRango(string $tabla,array $fechas,array $montos,string $desde,string $hasta): float
    {
        if (! Schema::hasTable($tabla)) return 0.0;
        $fecha = $this->columna($tabla, $fechas);
        $monto = $this->columna($tabla, $montos);
        if (! $monto) return 0.0;

        $q = DB::table($tabla);
        if (Schema::hasColumn($tabla, 'establecimiento_id')) {
            $q->where('establecimiento_id', $this->establecimientoId());
        }
        if ($tabla === 'gastos') {
            AccesoGastosSupervisor::filtrar($q, 'gastos');
        }
        if ($fecha) {
            $q->whereBetween(DB::raw("DATE($fecha)"), [$desde, $hasta]);
        }

        return (float) $q->sum($monto);
    }

    private function columna(string $tabla,array $candidatas): ?string
    {
        if(!Schema::hasTable($tabla))return null;$cols=array_flip(Schema::getColumnListing($tabla));foreach($candidatas as $c)if(isset($cols[$c]))return $c;return null;
    }

    private function rango(Request $request): array
    {
        $request->validate(['desde'=>['nullable','date'],'hasta'=>['nullable','date','after_or_equal:desde']]);
        $desde=$request->input('desde')?:now('America/Lima')->startOfMonth()->toDateString();
        $hasta=$request->input('hasta')?:now('America/Lima')->toDateString();
        return [$desde,$hasta];
    }

    private function etiqueta(string $campo): string
    {
        return [
            'fecha_control' => 'Fecha',
            'efectivo_dia_anterior' => 'Esperado del día anterior',
            'efectivo_total_dia' => 'Efectivo del día',
            'otros_ingresos_caja' => 'Otros ingresos a Caja',
            'digital_diario' => 'Digital',
            'gastos_efectivo_dia' => 'Gastos en efectivo',
            'compras_efectivo_dia' => 'Compras en efectivo',
            'depositos_banco' => 'Depósitos',
            'efectivo_esperado' => 'Esperado',
            'efectivo_contado' => 'Contado',
            'diferencia' => 'Diferencia',
            'observaciones' => 'Observaciones',
        ][$campo] ?? ucfirst(str_replace('_',' ',$campo));
    }

    private function vacio(string $titulo): array
    {
        return ['titulo'=>$titulo,'columnas'=>[],'filas'=>collect()];
    }

    private function autorizarReportes(): void
    {
        $this->autorizar(['ADMINISTRADOR','GERENCIA','SUPERVISOR','CONTABILIDAD','LECTURA']);
    }
}
