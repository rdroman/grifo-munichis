<?php

namespace App\Http\Controllers;

use App\Services\ServicioCapturasCompetencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ControladorCompetenciaApi extends ControladorBaseGrifo
{
    /** Catálogo informativo de productos de Facilito: no modifica el catálogo de ventas. */
    private const COMBUSTIBLES = [
        'gasolina_84' => ['nombre' => 'Gasolina 84', 'producto_id' => 1],
        'diesel_b5_uv' => ['nombre' => 'Diésel B5 UV', 'producto_id' => 3],
        'gasolina_regular' => ['nombre' => 'Gasolina Regular', 'producto_id' => 2],
        'gasolina_premium' => ['nombre' => 'Gasolina Premium', 'producto_id' => null],
    ];
    private function acceso(): void
    {
        $this->autorizar(['ADMINISTRADOR', 'GERENCIA', 'SUPERVISOR', 'CONTABILIDAD', 'LECTURA']);
    }

    private function admin(): void
    {
        $this->autorizar(['ADMINISTRADOR']);
    }

    public function indice(Request $request)
    {
        $this->acceso();
        $capturas = DB::table('competencia_capturas')->orderByDesc('fecha_jornada')->limit(400)->get();
        $captura = $request->filled('captura') ? $capturas->firstWhere('id', (int) $request->input('captura')) : $capturas->first();
        $zona = in_array($request->query('zona'), ['todos', 'cerca', 'directos'], true) ? $request->query('zona') : 'cerca';
        $datos = $captura ? $this->datosCaptura((int) $captura->id, $zona) : ['filas'=>[], 'resumen'=>[], 'propios'=>[]];
        // Filtros del ranking: solo afectan la lectura, nunca precios ni capturas.
        $productoRanking = (string) $request->query('producto', 'gasolina_regular');
        if (!array_key_exists($productoRanking, self::COMBUSTIBLES)) {
            $productoRanking = 'gasolina_regular';
        }
        $ordenRanking = (string) $request->query('orden', 'asc');
        if (!in_array($ordenRanking, ['asc', 'desc'], true)) {
            $ordenRanking = 'asc';
        }
        $ranking = $this->rankingPorPrecio($datos['filas'], $productoRanking, $ordenRanking);
        $tendencia = $this->tendencia($capturas->take(24)->reverse()->values());
        $periodos = $this->resumenPeriodos($capturas);
        $intentos = DB::table('competencia_intentos')->orderByDesc('id')->limit(12)->get();
        $ahora = now(config('competencia_apisted.zona_horaria', 'America/Lima'));
        // Desde v1.5 no hay cupos semanales: se permite una captura válida por día (Lima).
        $yaCapturadoHoy = DB::table('competencia_capturas')
            ->where('fecha_jornada', $ahora->toDateString())->exists();
        $tokenConfigurado = trim((string) config('competencia_apisted.token', '')) !== '';
        $puedeCapturar = !$yaCapturadoHoy && $tokenConfigurado;
        $motivoBloqueo = !$tokenConfigurado
            ? 'Falta configurar el token de ApisTED en este servidor.'
            : ($yaCapturadoHoy ? 'La captura de hoy ya fue guardada. La próxima consulta estará disponible mañana.' : null);
        // Comparar exclusivamente con la observación cronológicamente anterior a la elegida.
        // El cálculo es de lectura; no modifica registros históricos.
        $capturaAnterior = $captura
            ? DB::table('competencia_capturas')->where('fecha_jornada', '<', $captura->fecha_jornada)
                ->orderByDesc('fecha_jornada')->first()
            : null;
        $variaciones = $this->variacionesPrecios($captura ? $this->filas((int) $captura->id) : [], $capturaAnterior);
        // El ranking ya existe; adjuntar variación al producto elegido sin cambiar los puestos.
        foreach ($ranking['filas'] as &$participante) {
            $participante['variacion'] = $variaciones['por_codigo'][$participante['codigo']][$productoRanking] ?? null;
        }
        unset($participante);
        // Historial de un grifo por codigo Osinergmin: solo lectura, maximo 40 observaciones.
        $opcionesGrifos = $captura ? $this->filas((int) $captura->id) : [];
        $codigoGrifo = (string) $request->query('grifo', '');
        $grifoSeleccionado = null;
        foreach ($opcionesGrifos as $opcion) {
            if ($codigoGrifo !== '' && $codigoGrifo === (string) $opcion->codigo) {
                $grifoSeleccionado = $opcion;
                break;
            }
        }
        $historicoGrifo = $grifoSeleccionado ? $this->historicoPorGrifo((string) $grifoSeleccionado->codigo) : [];
        $preciosLocales = DB::table('vw_precios_venta_vigentes')
            ->where('establecimiento_id', $this->establecimientoId())
            ->whereIn('producto_id', [1, 2, 3])->pluck('precio', 'producto_id')->all();
        $catalogo = self::COMBUSTIBLES;
        return view('competencia.panel', compact('capturas', 'captura', 'zona', 'datos', 'tendencia', 'periodos', 'intentos', 'puedeCapturar', 'preciosLocales', 'motivoBloqueo', 'catalogo', 'productoRanking', 'ordenRanking', 'ranking', 'yaCapturadoHoy', 'variaciones', 'opcionesGrifos', 'grifoSeleccionado', 'historicoGrifo'));
    }

    private function filas(int $capturaId): array
    {
        $establecimientos = DB::table('competencia_establecimientos_captura as e')
            ->leftJoin('competencia_preferencias as f', 'f.codigo_osinergmin', '=', 'e.codigo_osinergmin')
            ->where('e.captura_id', $capturaId)
            ->orderBy('e.razon_social')
            ->get(['e.*', 'f.categoria']);
        $precios = DB::table('competencia_precios_captura as p')
            ->join('competencia_establecimientos_captura as e', 'e.id', '=', 'p.establecimiento_captura_id')
            ->where('e.captura_id', $capturaId)
            ->get(['p.establecimiento_captura_id', 'p.codigo_producto', 'p.precio']);
        $porEstablecimiento = [];
        foreach ($precios as $p) {
            $porEstablecimiento[$p->establecimiento_captura_id][$p->codigo_producto] = (float) $p->precio;
        }
        $propia = $establecimientos->firstWhere('codigo_osinergmin', config('competencia_apisted.codigo_munichis', '102201'));
        $resultado = [];
        foreach ($establecimientos as $e) {
            $distancia = $propia ? $this->distancia($propia->latitud, $propia->longitud, $e->latitud, $e->longitud) : null;
            $categoria = $e->categoria ?: 'AUTO';
            $directo = $categoria === 'DIRECTO' || ($categoria === 'AUTO' && $distancia !== null && $distancia <= (float) config('competencia_apisted.radio_km', 5));
            $resultado[] = (object) [
                'codigo' => $e->codigo_osinergmin,
                'nombre' => $e->razon_social,
                'direccion' => $e->direccion,
                'es_munichis' => (bool) $e->es_munichis,
                'categoria' => $categoria,
                'directo' => $directo,
                'distancia' => $distancia,
                'precios' => $porEstablecimiento[$e->id] ?? [],
            ];
        }
        return $resultado;
    }

    private function datosCaptura(int $capturaId, string $zona): array
    {
        $filas = $this->filas($capturaId);
        $propia = collect($filas)->firstWhere('es_munichis', true);
        $visibles = array_values(array_filter($filas, function ($f) use ($zona) {
            if ($f->categoria === 'EXCLUIR' && !$f->es_munichis) return false;
            if ($f->es_munichis) return true;
            if ($zona === 'directos') return $f->directo;
            if ($zona === 'cerca') return $f->distancia !== null && $f->distancia <= 5;
            return true;
        }));
        $resumen = [];
        foreach (self::COMBUSTIBLES as $codigo => $meta) {
            $valores = [];
            foreach ($visibles as $f) {
                if (!$f->es_munichis && isset($f->precios[$codigo])) $valores[] = $f->precios[$codigo];
            }
            sort($valores, SORT_NUMERIC);
            $n = count($valores);
            $propio = $propia->precios[$codigo] ?? null;
            $resumen[$codigo] = [
                'nombre' => $meta['nombre'], 'n' => $n,
                'minimo' => $n ? $valores[0] : null,
                'maximo' => $n ? $valores[$n-1] : null,
                'promedio' => $n ? array_sum($valores)/$n : null,
                'mediana' => $n ? ($valores[intdiv($n-1,2)] + $valores[intdiv($n,2)])/2 : null,
                'propio' => $propio,
                'brecha' => $n && $propio !== null ? $propio - array_sum($valores)/$n : null,
            ];
        }
        return ['filas' => $visibles, 'resumen' => $resumen, 'propios' => $propia?->precios ?? []];
    }


    /**
     * Compara precios de la captura elegida con la captura válida inmediatamente anterior.
     * Llave estable: código Osinergmin + código de combustible. Solo compara productos
     * presentes en AMBAS capturas y redondea a centavos (unidad mostrada en pantalla).
     * Para las alertas se utilizan TODOS los grifos de Yurimaguas, sin filtro de radio;
     * los filtros de ámbito solo controlan qué filas son visibles en el comparador.
     */
    private function variacionesPrecios(array $filasActuales, ?object $capturaAnterior): array
    {
        $resultado = [
            'anterior' => $capturaAnterior,
            'por_codigo' => [],
            'cambios' => [],
            'grifos_afectados' => 0,
            'subidas' => 0,
            'bajadas' => 0,
            // Totales de competidores, sin contar Munichis, con productos comparables.
            'grifos_comparables' => 0,
            'grifos_estables' => 0,
            'grifos_con_subida' => 0,
            'grifos_con_bajada' => 0,
            'directos_con_cambios' => 0,
            'cambios_directos' => [],
            'por_producto' => array_fill_keys(array_keys(self::COMBUSTIBLES), ['subidas' => 0, 'bajadas' => 0]),
        ];
        if (!$capturaAnterior) return $resultado;

        $anteriores = [];
        foreach ($this->filas((int) $capturaAnterior->id) as $fila) {
            $anteriores[(string) $fila->codigo] = $fila->precios;
        }
        foreach ($filasActuales as $fila) {
            $codigo = (string) $fila->codigo;
            if (!isset($anteriores[$codigo])) continue;
            $comparablesGrifo = 0;
            $subidasGrifo = 0;
            $bajadasGrifo = 0;
            foreach (self::COMBUSTIBLES as $producto => $meta) {
                if (!isset($fila->precios[$producto]) || !isset($anteriores[$codigo][$producto])) {
                    continue; // Nueva oferta o desaparición: no afirmar cambio de precio.
                }
                $comparablesGrifo++;
                $centavosAntes = (int) round((float) $anteriores[$codigo][$producto] * 100);
                $centavosAhora = (int) round((float) $fila->precios[$producto] * 100);
                $deltaCentavos = $centavosAhora - $centavosAntes;
                if ($deltaCentavos === 0) continue;
                if ($deltaCentavos > 0) $subidasGrifo++;
                else $bajadasGrifo++;
                if (!$fila->es_munichis) {
                    $resultado['por_producto'][$producto][$deltaCentavos > 0 ? 'subidas' : 'bajadas']++;
                }
                $cambio = [
                    'codigo' => $codigo,
                    'nombre' => (string) $fila->nombre,
                    'producto' => $producto,
                    'combustible' => $meta['nombre'],
                    'anterior' => $centavosAntes / 100,
                    'actual' => $centavosAhora / 100,
                    'variacion' => $deltaCentavos / 100,
                    'tipo' => $deltaCentavos > 0 ? 'subida' : 'bajada',
                    'es_munichis' => (bool) $fila->es_munichis,
                    'directo' => (bool) $fila->directo && $fila->categoria !== 'EXCLUIR',
                    'distancia' => $fila->distancia,
                ];
                $resultado['por_codigo'][$codigo][$producto] = $cambio;
                $resultado['cambios'][] = $cambio;
                $resultado[$deltaCentavos > 0 ? 'subidas' : 'bajadas']++;
            }
            if ($comparablesGrifo > 0 && !$fila->es_munichis) {
                $resultado['grifos_comparables']++;
                if ($subidasGrifo === 0 && $bajadasGrifo === 0) $resultado['grifos_estables']++;
                if ($subidasGrifo > 0) $resultado['grifos_con_subida']++;
                if ($bajadasGrifo > 0) $resultado['grifos_con_bajada']++;
                if ($fila->directo && $fila->categoria !== 'EXCLUIR' && ($subidasGrifo + $bajadasGrifo) > 0) {
                    $resultado['directos_con_cambios']++;
                }
            }
        }
        $resultado['grifos_afectados'] = count($resultado['por_codigo']);
        usort($resultado['cambios'], static function ($a, $b) {
            $comparacion = abs($b['variacion']) <=> abs($a['variacion']);
            return $comparacion !== 0 ? $comparacion : strcmp($a['nombre'], $b['nombre']);
        });
        // Alertas relevantes para el negocio, sin incluir al propio Munichis.
        $resultado['cambios_directos'] = array_values(array_filter($resultado['cambios'],
            static fn (array $c) => $c['directo'] && !$c['es_munichis']));
        return $resultado;
    }

    /**
     * Historial de hasta 40 observaciones por establecimiento, identificado por codigo
     * Osinergmin. Es independiente del filtro geografico y no modifica datos.
     * Una ausencia de precio se marca como no informado, sin asumir que subio o bajo.
     */
    private function historicoPorGrifo(string $codigo): array
    {
        $estaciones = DB::table('competencia_establecimientos_captura as e')
            ->join('competencia_capturas as c', 'c.id', '=', 'e.captura_id')
            ->where('e.codigo_osinergmin', $codigo)
            ->orderByDesc('c.fecha_jornada')
            ->limit(40)
            ->get(['e.id', 'e.razon_social', 'c.fecha_jornada', 'c.fecha_consulta']);
        if ($estaciones->isEmpty()) return [];
        $precios = DB::table('competencia_precios_captura')
            ->whereIn('establecimiento_captura_id', $estaciones->pluck('id')->all())
            ->get(['establecimiento_captura_id', 'codigo_producto', 'precio']);
        $porEstacion = [];
        foreach ($precios as $p) {
            $porEstacion[$p->establecimiento_captura_id][$p->codigo_producto] = (float) $p->precio;
        }
        $filas = [];
        foreach ($estaciones as $estacion) {
            $filas[] = [
                'fecha' => $estacion->fecha_jornada,
                'hora' => $estacion->fecha_consulta,
                'precios' => $porEstacion[$estacion->id] ?? [],
                'variaciones' => [],
            ];
        }
        // Se muestran de mas reciente a mas antiguo; la siguiente fila es la anterior.
        foreach ($filas as $i => &$fila) {
            if (!isset($filas[$i + 1])) continue;
            foreach (self::COMBUSTIBLES as $producto => $meta) {
                $actual = $fila['precios'][$producto] ?? null;
                $anterior = $filas[$i + 1]['precios'][$producto] ?? null;
                if ($actual === null || $anterior === null) continue;
                $cambioCentavos = (int) round($actual * 100) - (int) round($anterior * 100);
                if ($cambioCentavos !== 0) $fila['variaciones'][$producto] = $cambioCentavos / 100;
            }
        }
        unset($fila);
        return $filas;
    }

    /**
     * Ranking por precio del combustible y ambito seleccionados. Incluye a Munichis si
     * reporta ese combustible; EXCLUIR y el filtro geografico ya se aplicaron en datosCaptura.
     * Los precios se comparan con precision de 4 decimales (escala de la columna SQL).
     * Empates usan clasificacion competitiva: 1, 1, 3 (no se inventan desempates).
     */
    private function rankingPorPrecio(array $filasVisibles, string $producto, string $orden): array
    {
        $participantes = [];
        $precioPropio = null;
        foreach ($filasVisibles as $fila) {
            if (!isset($fila->precios[$producto]) || !is_numeric($fila->precios[$producto])) {
                continue;
            }
            $precio = (float) $fila->precios[$producto];
            if ($precio <= 0 || !is_finite($precio)) {
                continue;
            }
            $precioEscala = (int) round($precio * 10000);
            if ($fila->es_munichis) {
                $precioPropio = $precio;
            }
            $participantes[] = [
                'codigo' => (string) $fila->codigo,
                'nombre' => (string) $fila->nombre,
                'distancia' => $fila->distancia,
                'es_munichis' => (bool) $fila->es_munichis,
                'precio' => $precio,
                'precio_escala' => $precioEscala,
            ];
        }

        usort($participantes, static function (array $a, array $b) use ($orden): int {
            $comparacion = $a['precio_escala'] <=> $b['precio_escala'];
            if ($orden === 'desc') $comparacion *= -1;
            if ($comparacion !== 0) return $comparacion;
            $comparacion = strnatcasecmp($a['nombre'], $b['nombre']);
            return $comparacion !== 0 ? $comparacion : strcmp($a['codigo'], $b['codigo']);
        });

        $posicionMunichis = null;
        $puestoAnterior = 0;
        $precioAnterior = null;
        foreach ($participantes as $i => &$p) {
            if ($precioAnterior !== $p['precio_escala']) {
                $puestoAnterior = $i + 1;
            }
            $p['puesto'] = $puestoAnterior;
            $p['diferencia'] = $precioPropio === null ? null : round($p['precio'] - $precioPropio, 4);
            if ($p['es_munichis']) $posicionMunichis = $puestoAnterior;
            $precioAnterior = $p['precio_escala'];
            unset($p['precio_escala']);
        }
        unset($p);

        return [
            'filas' => $participantes,
            'total' => count($participantes),
            'competidores' => count(array_filter($participantes, static fn ($p) => !$p['es_munichis'])),
            'precio_munichis' => $precioPropio,
            'puesto_munichis' => $posicionMunichis,
        ];
    }

    private function distancia($lat1, $lon1, $lat2, $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) return null;
        $dlat = deg2rad((float) $lat2 - (float) $lat1);
        $dlon = deg2rad((float) $lon2 - (float) $lon1);
        $a = sin($dlat/2)**2 + cos(deg2rad((float) $lat1))*cos(deg2rad((float) $lat2))*sin($dlon/2)**2;
        return 6371 * 2 * asin(min(1, sqrt($a)));
    }

    private function tendencia($capturas): array
    {
        $historico = [];
        foreach ($capturas as $captura) {
            $datos = $this->datosCaptura((int) $captura->id, 'cerca');
            $fila = ['fecha' => $captura->fecha_jornada];
            foreach (self::COMBUSTIBLES as $codigo => $meta) {
                $fila[$codigo] = $datos['resumen'][$codigo]['promedio'] ?? null;
                $fila['munichis_'.$codigo] = $datos['resumen'][$codigo]['propio'] ?? null;
            }
            $historico[] = $fila;
        }
        return $historico;
    }

    private function resumenPeriodos($capturas): array
    {
        if ($capturas->isEmpty()) return ['meses' => [], 'anios' => []];
        $ids = $capturas->pluck('id')->all();
        // Usar las coordenadas de Munichis de cada captura, no las de una fecha distinta.
        $propias = DB::table('competencia_establecimientos_captura')
            ->whereIn('captura_id', $ids)->where('es_munichis', 1)
            ->get(['captura_id', 'latitud', 'longitud'])->keyBy('captura_id');

        $filas = DB::table('competencia_precios_captura as p')
            ->join('competencia_establecimientos_captura as e', 'e.id', '=', 'p.establecimiento_captura_id')
            ->join('competencia_capturas as c', 'c.id', '=', 'e.captura_id')
            ->leftJoin('competencia_preferencias as f', 'f.codigo_osinergmin', '=', 'e.codigo_osinergmin')
            ->whereIn('c.id', $ids)->where('e.es_munichis', 0)
            ->whereIn('p.codigo_producto', array_keys(self::COMBUSTIBLES))
            ->where(function ($q) { $q->whereNull('f.categoria')->orWhere('f.categoria', '<>', 'EXCLUIR'); })
            ->get(['c.id as captura_id', 'c.fecha_jornada', 'p.codigo_producto', 'p.precio', 'e.latitud', 'e.longitud']);

        $porDia = [];
        foreach ($filas as $r) {
            $propia = $propias->get($r->captura_id);
            if (!$propia) continue;
            $distancia = $this->distancia($propia->latitud, $propia->longitud, $r->latitud, $r->longitud);
            if ($distancia === null || $distancia > 5) continue;
            $porDia[$r->fecha_jornada][$r->codigo_producto][] = (float) $r->precio;
        }

        $acumulados = ['meses' => [], 'anios' => []];
        foreach ($porDia as $fecha => $combustibles) {
            foreach ($combustibles as $codigo => $valores) {
                $promedioDeCaptura = array_sum($valores) / count($valores);
                foreach (['meses' => substr($fecha, 0, 7), 'anios' => substr($fecha, 0, 4)] as $tipo => $periodo) {
                    $acumulados[$tipo][$periodo][$codigo][] = $promedioDeCaptura;
                }
            }
        }
        foreach ($acumulados as $tipo => $grupos) {
            krsort($grupos);
            foreach ($grupos as $periodo => $datos) {
                foreach (self::COMBUSTIBLES as $codigo => $meta) {
                    $valores = $datos[$codigo] ?? [];
                    $grupos[$periodo][$codigo] = count($valores) ? array_sum($valores) / count($valores) : null;
                    $grupos[$periodo][$codigo.'_capturas'] = count($valores);
                }
            }
            $acumulados[$tipo] = $grupos;
        }
        return $acumulados;
    }

    public function capturar(ServicioCapturasCompetencia $servicio)
    {
        $this->admin();
        try {
            $r = $servicio->capturarManual();
            return redirect()->route('competencia.indice')->with('exito', $r['estado'] === 'OK' ? "Captura guardada: {$r['establecimientos']} estaciones y {$r['precios']} precios." : $r['mensaje']);
        } catch (Throwable $e) {
            return redirect()->route('competencia.indice')->with('error', $e->getMessage());
        }
    }

    public function importar(Request $request, ServicioCapturasCompetencia $servicio)
    {
        $this->admin();
        $request->validate(['archivo' => ['required', 'file', 'mimes:json,txt', 'max:4096']]);
        try {
            $r = $servicio->importarJson($request->file('archivo')->get());
            return redirect()->route('competencia.indice')->with('exito', $r['estado'] === 'OK' ? "JSON importado: {$r['establecimientos']} establecimientos y {$r['precios']} precios." : $r['mensaje']);
        } catch (Throwable $e) {
            return redirect()->route('competencia.indice')->with('error', $e->getMessage());
        }
    }

    public function preferencia(Request $request)
    {
        $this->admin();
        $data = $request->validate([
            'codigo_osinergmin' => ['required', 'regex:/^[0-9]{1,30}$/'],
            'categoria' => ['required', 'in:AUTO,DIRECTO,GENERAL,EXCLUIR'],
        ]);
        if ($data['codigo_osinergmin'] === (string) config('competencia_apisted.codigo_munichis')) {
            abort(422, 'Munichis no puede excluirse del análisis.');
        }
        DB::table('competencia_preferencias')->updateOrInsert(
            ['codigo_osinergmin' => $data['codigo_osinergmin']],
            ['categoria' => $data['categoria'], 'actualizado_en' => now()]
        );
        return back()->with('exito', 'Clasificación actualizada.');
    }

    private function celdaCsv(?string $texto): string
    {
        $texto = (string) $texto;
        return preg_match('/^\s*[=+@-]/u', $texto) ? "'".$texto : $texto;
    }

    public function exportar(Request $request)
    {
        $this->acceso();
        $captura = DB::table('competencia_capturas')->find((int) $request->query('captura'));
        if (!$captura) abort(404);
        $filas = $this->filas((int) $captura->id);
        $capturaAnterior = DB::table('competencia_capturas')
            ->where('fecha_jornada', '<', $captura->fecha_jornada)
            ->orderByDesc('fecha_jornada')->first();
        $variaciones = $this->variacionesPrecios($filas, $capturaAnterior);
        $nombre = 'Competencia_Yurimaguas_'.$captura->fecha_jornada.'.csv';
        return response()->streamDownload(function () use ($filas, $captura, $variaciones, $capturaAnterior) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Fecha', 'Comparado con', 'Código Osinergmin', 'Establecimiento', 'Dirección',
                'Distancia km', 'Categoría', 'Gasolina 84', 'Diésel B5', 'Gasolina Regular',
                'Gasolina Premium', 'Variación G84 S/', 'Variación B5 S/',
                'Variación Regular S/', 'Variación Premium S/',
            ], ';');
            foreach ($filas as $f) {
                $cambios = $variaciones['por_codigo'][(string) $f->codigo] ?? [];
                $columnaCambio = static function (array $cambios, string $codigo): string {
                    return isset($cambios[$codigo])
                        ? number_format($cambios[$codigo]['variacion'], 2, ',', '') : '';
                };
                fputcsv($out, [
                    $captura->fecha_jornada, $capturaAnterior?->fecha_jornada ?? '',
                    $f->codigo, $this->celdaCsv($f->nombre), $this->celdaCsv($f->direccion),
                    $f->distancia === null ? '' : number_format($f->distancia, 2, ',', ''), $f->categoria,
                    $f->precios['gasolina_84'] ?? '', $f->precios['diesel_b5_uv'] ?? '',
                    $f->precios['gasolina_regular'] ?? '', $f->precios['gasolina_premium'] ?? '',
                    $columnaCambio($cambios, 'gasolina_84'),
                    $columnaCambio($cambios, 'diesel_b5_uv'),
                    $columnaCambio($cambios, 'gasolina_regular'),
                    $columnaCambio($cambios, 'gasolina_premium'),
                ], ';');
            }
            fclose($out);
        }, $nombre, ['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
