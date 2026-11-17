<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fechaCorte = '2026-09-11';
$mensaje = 'Validación histórica masiva: turno anterior al 11/09/2026 corroborado.';

foreach ([
    'turnos_operativos' => ['id','fecha_operacion'],
    'resumen_financiero_turno' => [
        'turno_operativo_id','efectivo_entregado','efectivo_administrador',
        'digital_operador','digital_administrador','estado_validacion_digital'
    ],
] as $tabla => $columnas) {
    if (!Schema::hasTable($tabla)) {
        fwrite(STDERR, "ERROR: no existe la tabla {$tabla}.\n");
        exit(1);
    }
    foreach ($columnas as $columna) {
        if (!Schema::hasColumn($tabla, $columna)) {
            fwrite(STDERR, "ERROR: falta {$tabla}.{$columna}.\n");
            exit(1);
        }
    }
}

$filas = DB::table('turnos_operativos as t')
    ->join('resumen_financiero_turno as rf', 'rf.turno_operativo_id', '=', 't.id')
    ->where('t.fecha_operacion', '<', $fechaCorte)
    ->orderBy('t.fecha_operacion')
    ->orderBy('t.id')
    ->get([
        't.id as turno_operativo_id',
        't.fecha_operacion',
        'rf.efectivo_entregado',
        'rf.efectivo_administrador',
        'rf.digital_operador',
        'rf.digital_administrador',
        'rf.estado_validacion_digital',
        ...(Schema::hasColumn('resumen_financiero_turno', 'validado_efectivo_en') ? ['rf.validado_efectivo_en'] : []),
        ...(Schema::hasColumn('resumen_financiero_turno', 'validado_digital_en') ? ['rf.validado_digital_en'] : []),
        ...(Schema::hasColumn('resumen_financiero_turno', 'observacion_validacion_efectivo') ? ['rf.observacion_validacion_efectivo'] : []),
        ...(Schema::hasColumn('resumen_financiero_turno', 'observacion_validacion_digital') ? ['rf.observacion_validacion_digital'] : []),
    ]);

$sinResumen = DB::table('turnos_operativos as t')
    ->leftJoin('resumen_financiero_turno as rf', 'rf.turno_operativo_id', '=', 't.id')
    ->where('t.fecha_operacion', '<', $fechaCorte)
    ->whereNull('rf.turno_operativo_id')
    ->count();

echo "Fecha de corte : {$fechaCorte}\n";
echo "Criterio       : fecha_operacion < {$fechaCorte}\n";
echo "Turnos con resumen financiero: {$filas->count()}\n";
echo "Turnos sin resumen financiero: {$sinResumen}\n";

if ($filas->isEmpty()) {
    echo "No hay turnos historicos que actualizar.\n";
    exit(0);
}

$dir = storage_path('app/backups');
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$rutaBackup = $dir . DIRECTORY_SEPARATOR . 'validacion_turnos_historicos_' . now()->format('Ymd_His') . '.json';
file_put_contents($rutaBackup, json_encode($filas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Backup JSON    : {$rutaBackup}\n";

$ahora = now();
$actualizados = 0;
$efectivoCopiado = 0;
$digitalCopiado = 0;
$yaCompletos = 0;

DB::transaction(function () use (
    $filas, $mensaje, $ahora,
    &$actualizados, &$efectivoCopiado, &$digitalCopiado, &$yaCompletos
) {
    foreach ($filas as $fila) {
        $cambios = [];

        if ($fila->efectivo_administrador === null) {
            $cambios['efectivo_administrador'] = (float)($fila->efectivo_entregado ?? 0);
            $efectivoCopiado++;
        }

        if ($fila->digital_administrador === null) {
            $cambios['digital_administrador'] = (float)($fila->digital_operador ?? 0);
            $digitalCopiado++;
        }

        if ((string)$fila->estado_validacion_digital !== 'VALIDADO') {
            $cambios['estado_validacion_digital'] = 'VALIDADO';
        }

        if (Schema::hasColumn('resumen_financiero_turno', 'validado_efectivo_en')
            && empty($fila->validado_efectivo_en)) {
            $cambios['validado_efectivo_en'] = $ahora;
        }

        if (Schema::hasColumn('resumen_financiero_turno', 'validado_digital_en')
            && empty($fila->validado_digital_en)) {
            $cambios['validado_digital_en'] = $ahora;
        }

        if (Schema::hasColumn('resumen_financiero_turno', 'observacion_validacion_efectivo')) {
            $actual = trim((string)($fila->observacion_validacion_efectivo ?? ''));
            if (!str_contains($actual, $mensaje)) {
                $cambios['observacion_validacion_efectivo'] = $actual === '' ? $mensaje : ($actual . ' | ' . $mensaje);
            }
        }

        if (Schema::hasColumn('resumen_financiero_turno', 'observacion_validacion_digital')) {
            $actual = trim((string)($fila->observacion_validacion_digital ?? ''));
            if (!str_contains($actual, $mensaje)) {
                $cambios['observacion_validacion_digital'] = $actual === '' ? $mensaje : ($actual . ' | ' . $mensaje);
            }
        }

        if (!$cambios) {
            $yaCompletos++;
            continue;
        }

        DB::table('resumen_financiero_turno')
            ->where('turno_operativo_id', $fila->turno_operativo_id)
            ->update($cambios);

        $actualizados++;
    }
});

$pendientes = DB::table('turnos_operativos as t')
    ->join('resumen_financiero_turno as rf', 'rf.turno_operativo_id', '=', 't.id')
    ->where('t.fecha_operacion', '<', $fechaCorte)
    ->where(function ($q) {
        $q->whereNull('rf.efectivo_administrador')
          ->orWhereNull('rf.digital_administrador')
          ->orWhere('rf.estado_validacion_digital', '<>', 'VALIDADO');
    })
    ->count();

echo "\nRESULTADO\n";
echo "---------\n";
echo "Turnos actualizados                : {$actualizados}\n";
echo "Efectivo admin copiado del operador: {$efectivoCopiado}\n";
echo "Digital admin copiado del operador : {$digitalCopiado}\n";
echo "Turnos que ya estaban completos    : {$yaCompletos}\n";
echo "Pendientes historicos restantes    : {$pendientes}\n";
echo "Turnos sin resumen financiero      : {$sinResumen}\n";

if ($pendientes > 0) {
    fwrite(STDERR, "ADVERTENCIA: quedaron {$pendientes} turnos historicos con validacion incompleta.\n");
    exit(2);
}

echo "\nVALIDACION HISTORICA COMPLETADA.\n";
