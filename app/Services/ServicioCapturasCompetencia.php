<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Capturas independientes de ventas, caja y del histórico manual de competencia. */
class ServicioCapturasCompetencia
{
    /** Método anterior usado por tareas programadas: deliberadamente deshabilitado. */
    public function capturarApi(): array
    {
        throw new RuntimeException('La captura automática está deshabilitada. Ingresa al módulo Competencia y usa el botón manual.');
    }

    /** Única vía de consulta API: botón del panel, autenticado como ADMINISTRADOR. */
    public function capturarManual(): array
    {
        $ahora = Carbon::now(config('competencia_apisted.zona_horaria', 'America/Lima'));
        $fecha = $ahora->toDateString();
        try {
            return $this->conBloqueoDiario($fecha, function () use ($fecha) {
                $this->validarCupoDiario($fecha);
                $token = trim((string) config('competencia_apisted.token', ''));
                if ($token === '') {
                    throw new RuntimeException('Falta APISTED_TOKEN en la configuración del servidor.');
                }
                $paginas = [];
                $totalPaginas = 1;
                for ($pagina = 1; $pagina <= $totalPaginas; $pagina++) {
                    if ($pagina > 20) {
                        throw new RuntimeException('La API devolvió demasiadas páginas; revisar antes de guardar.');
                    }
                    $respuesta = Http::withToken($token)->acceptJson()->asJson()
                        ->connectTimeout(10)->timeout(35)->post(config('competencia_apisted.url'), [
                            'ubigeo' => config('competencia_apisted.ubigeo'),
                            'pagina' => $pagina,
                            'por_pagina' => 200,
                        ]);
                    if (!$respuesta->successful()) {
                        $codigo = $respuesta->status();
                        throw new RuntimeException("La consulta ApisTED falló (HTTP {$codigo}).");
                    }
                    $data = $respuesta->json();
                    if (!is_array($data)) {
                        throw new RuntimeException('La API no devolvió un objeto JSON válido.');
                    }
                    if ($pagina === 1) {
                        $totalPaginas = (int) ($data['paginas'] ?? 0);
                        if ($totalPaginas < 1 || $totalPaginas > 20) {
                            throw new RuntimeException('Número de páginas no válido.');
                        }
                    }
                    if ((int) ($data['pagina'] ?? 0) !== $pagina || (int) ($data['paginas'] ?? 0) !== $totalPaginas) {
                        throw new RuntimeException('Paginación incoherente entre respuestas.');
                    }
                    $paginas[] = $data;
                }
                return $this->guardarPaginas($paginas, 'API', $fecha);
            });
        } catch (Throwable $e) {
            $this->intento($fecha, 'API', 'ERROR', $e->getMessage());
            throw $e;
        }
    }

    /** Permite recuperar un JSON de SoapUI sin consumir llamadas a ApisTED. */
    public function importarJson(string $contenido): array
    {
        $documento = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($documento)) {
            throw new RuntimeException('El archivo no contiene un objeto JSON.');
        }
        $fecha = $this->fechaDeRespuesta($documento)->toDateString();
        try {
            return $this->conBloqueoDiario($fecha, function () use ($documento, $fecha) {
                $this->validarCupoDiario($fecha);
                return $this->guardarPaginas([$documento], 'JSON', $fecha);
            });
        } catch (Throwable $e) {
            $this->intento($fecha, 'JSON', 'ERROR', $e->getMessage());
            throw $e;
        }
    }

    private function fechaDeRespuesta(array $data): Carbon
    {
        $texto = (string) ($data['fecha_consulta'] ?? '');
        try {
            $fecha = Carbon::createFromFormat('!d/m/Y H:i:s', $texto, 'America/Lima');
            if (!$fecha || $fecha->format('d/m/Y H:i:s') !== $texto) {
                throw new RuntimeException('Fecha API no válida.');
            }
            if ($fecha->greaterThan(Carbon::now('America/Lima')->addMinutes(15))) {
                throw new RuntimeException('La API devuelve una fecha futura.');
            }
            return $fecha;
        } catch (Throwable $e) {
            throw new RuntimeException('Formato de fecha_consulta inválido (se espera dd/mm/aaaa HH:mm:ss).');
        }
    }

    private function guardarPaginas(array $paginas, string $origen, string $fechaJornada): array
    {
        // Segunda verificación dentro del bloqueo (también protege importaciones JSON).
        $this->validarCupoDiario($fechaJornada);
        $primera = $paginas[0];
        if ((string) ($primera['ubigeo'] ?? '') !== '160201' ||
            strtoupper((string) ($primera['moneda'] ?? '')) !== 'PEN' ||
            (string) ($primera['unidad_medida'] ?? '') !== 'galón' ||
            ($primera['producto'] ?? null) !== null) {
            throw new RuntimeException('Ubigeo, moneda, unidad o filtro de producto inesperados.');
        }
        $fechaConsulta = $this->fechaDeRespuesta($primera);
        if ($fechaConsulta->toDateString() !== $fechaJornada) {
            throw new RuntimeException('La fecha de consulta no coincide con la jornada.');
        }
        $total = (int) ($primera['total'] ?? 0);
        if ($total < 1 || $total > 4000 || (int) ($primera['paginas'] ?? 0) !== count($paginas)) {
            throw new RuntimeException('Total o número de páginas inválidos.');
        }
        $estaciones = [];
        $cantidadPrecios = 0;
        foreach ($paginas as $pagina) {
            if ((string) ($pagina['ubigeo'] ?? '') !== '160201' || (int) ($pagina['total'] ?? -1) !== $total ||
                ($pagina['producto'] ?? null) !== null || !isset($pagina['establecimientos']) ||
                !is_array($pagina['establecimientos'])) {
                throw new RuntimeException('Respuesta incompleta o páginas incoherentes.');
            }
            if ($this->fechaDeRespuesta($pagina)->toDateString() !== $fechaJornada) {
                throw new RuntimeException('Las páginas tienen fechas diferentes.');
            }
            foreach ($pagina['establecimientos'] as $est) {
                if (!is_array($est)) throw new RuntimeException('Establecimiento inválido.');
                $codigo = (string) ($est['codigo_osinergmin'] ?? '');
                if (!preg_match('/^[0-9]{1,30}$/', $codigo) || isset($estaciones[$codigo])) {
                    throw new RuntimeException('Código Osinergmin faltante o duplicado.');
                }
                $nombre = trim((string) ($est['razon_social'] ?? ''));
                if ($nombre === '' || mb_strlen($nombre) > 180) {
                    throw new RuntimeException("Razón social inválida para {$codigo}.");
                }
                $precios = $est['precios'] ?? null;
                if (!is_array($precios) || count($precios) < 1) {
                    throw new RuntimeException("El establecimiento {$codigo} no tiene precios.");
                }
                $validados = [];
                foreach ($precios as $producto => $precio) {
                    if (!preg_match('/^[a-z0-9_]{1,50}$/', (string) $producto) ||
                        !is_numeric($precio) || !is_finite((float) $precio) || (float) $precio <= 0 || (float) $precio > 300) {
                        throw new RuntimeException("Precio o producto inválido para {$codigo}.");
                    }
                    $validados[$producto] = number_format((float) $precio, 4, '.', '');
                    $cantidadPrecios++;
                }
                $lat = $est['latitud'] ?? null;
                $lon = $est['longitud'] ?? null;
                $lat = is_numeric($lat) && abs((float) $lat) <= 90 ? (float) $lat : null;
                $lon = is_numeric($lon) && abs((float) $lon) <= 180 ? (float) $lon : null;
                $estaciones[$codigo] = [
                    'codigo' => $codigo, 'razon_social' => $nombre,
                    'direccion' => mb_substr((string) ($est['direccion'] ?? ''), 0, 255),
                    'latitud' => $lat, 'longitud' => $lon,
                    'precios' => $validados,
                ];
            }
        }
        if ($total !== count($estaciones)) {
            throw new RuntimeException("Respuesta incompleta: se esperaban {$total} establecimientos y llegaron ".count($estaciones).'.');
        }
        if (!isset($estaciones[(string) config('competencia_apisted.codigo_munichis', '102201')])) {
            throw new RuntimeException('No aparece Grifo Munichis (102201). Revisión manual requerida.');
        }
        $ultimo = DB::table('competencia_capturas')->orderByDesc('fecha_jornada')->first();
        $advertencia = null;
        if ($ultimo && $ultimo->establecimientos_guardados > 0 && count($estaciones) < $ultimo->establecimientos_guardados * 0.75) {
            $advertencia = 'El número de establecimientos cayó más de 25% respecto a la captura anterior.';
        }
        $documento = count($paginas) === 1 ? $primera : ['paginas_completas' => $paginas];
        $json = json_encode($documento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $json);
        $ruta = 'competencia/capturas/'.$fechaJornada.'-'.Str::uuid().'.json';
        if (!Storage::disk('local')->put($ruta, $json)) {
            throw new RuntimeException('No se pudo respaldar el JSON original.');
        }
        try {
            $id = DB::transaction(function () use ($fechaJornada, $fechaConsulta, $origen, $total, $estaciones, $cantidadPrecios, $advertencia, $ruta, $hash) {
                $id = DB::table('competencia_capturas')->insertGetId([
                    'fecha_jornada' => $fechaJornada,
                    'fecha_consulta' => $fechaConsulta->format('Y-m-d H:i:s'),
                    'origen' => $origen,
                    'total_api' => $total,
                    'establecimientos_guardados' => count($estaciones),
                    'precios_guardados' => $cantidadPrecios,
                    'archivo_json' => $ruta,
                    'sha256' => $hash,
                    'advertencia' => $advertencia,
                ]);
                foreach ($estaciones as $est) {
                    $estId = DB::table('competencia_establecimientos_captura')->insertGetId([
                        'captura_id' => $id,
                        'codigo_osinergmin' => $est['codigo'],
                        'razon_social' => $est['razon_social'],
                        'direccion' => $est['direccion'],
                        'latitud' => $est['latitud'],
                        'longitud' => $est['longitud'],
                        'es_munichis' => $est['codigo'] === (string) config('competencia_apisted.codigo_munichis', '102201'),
                    ]);
                    $filas = [];
                    foreach ($est['precios'] as $producto => $precio) {
                        $filas[] = ['establecimiento_captura_id' => $estId, 'codigo_producto' => $producto, 'precio' => $precio];
                    }
                    DB::table('competencia_precios_captura')->insert($filas);
                }
                return $id;
            }, 2);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($ruta);
            if ($this->existeCaptura($fechaJornada)) {
                return ['estado' => 'EXISTENTE', 'mensaje' => 'La jornada ya fue capturada por otra ejecución.'];
            }
            throw $e;
        }
        $this->intento($fechaJornada, $origen, 'OK', "Captura {$id}: ".count($estaciones).' establecimientos, '.$cantidadPrecios.' precios');
        return ['estado' => 'OK', 'id' => $id, 'establecimientos' => count($estaciones), 'precios' => $cantidadPrecios, 'advertencia' => $advertencia];
    }

    /** Una sola captura válida por fecha peruana, sin máximo semanal. */
    private function validarCupoDiario(string $fecha): void
    {
        $dia = Carbon::createFromFormat('!Y-m-d', $fecha, config('competencia_apisted.zona_horaria', 'America/Lima'));
        if (!$dia || $dia->format('Y-m-d') !== $fecha) {
            throw new RuntimeException('Fecha de captura inválida.');
        }
        if ($this->existeCaptura($fecha)) {
            throw new RuntimeException('Ya existe una captura válida en esa fecha. Máximo una captura por día.');
        }
    }

    /** Bloqueo MySQL por día, también protege contra doble clic simultáneo. */
    private function conBloqueoDiario(string $fecha, callable $operacion): array
    {
        $dia = Carbon::createFromFormat('!Y-m-d', $fecha, config('competencia_apisted.zona_horaria', 'America/Lima'));
        if (!$dia || $dia->format('Y-m-d') !== $fecha) {
            throw new RuntimeException('Fecha de captura inválida.');
        }
        $clave = 'munichis_competencia_dia_' . $dia->format('Ymd');
        $bloqueo = DB::selectOne('SELECT GET_LOCK(?, 10) AS adquirido', [$clave]);
        if ((int) ($bloqueo->adquirido ?? 0) !== 1) {
            throw new RuntimeException('Hay otra consulta en curso para esta fecha. Inténtalo cuando termine.');
        }
        try {
            return $operacion();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS liberado', [$clave]);
        }
    }

    private function existeCaptura(string $fecha): bool
    {
        return DB::table('competencia_capturas')->where('fecha_jornada', $fecha)->exists();
    }

    private function intento(string $fecha, string $origen, string $estado, string $detalle): void
    {
        try {
            DB::table('competencia_intentos')->insert([
                'fecha_jornada' => $fecha, 'origen' => $origen, 'estado' => $estado,
                'detalle' => mb_substr($detalle, 0, 255),
            ]);
        } catch (Throwable $e) {
            // No sustituir el error original si el registro de auditoría falla.
        }
    }
}
