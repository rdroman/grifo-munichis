<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

$base = dirname(__DIR__);

require $base . '/vendor/autoload.php';

$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tabla = 'controles_diarios_caja';

if (!Schema::hasTable($tabla)) {
    fwrite(STDERR, "ERROR: No existe la tabla {$tabla}.\n");
    exit(1);
}

$columnas = [
    'efectivo_dia_anterior' => ['nullable' => true,  'default' => null],
    'efectivo_total_dia'    => ['nullable' => true,  'default' => null],
    'gastos_efectivo_dia'   => ['nullable' => true,  'default' => null],
    'compras_efectivo_dia'  => ['nullable' => true,  'default' => null],
    'depositos_banco'        => ['nullable' => false, 'default' => 0],
    'efectivo_esperado'      => ['nullable' => true,  'default' => null],
];

$agregadas = [];

foreach ($columnas as $nombre => $opciones) {
    if (Schema::hasColumn($tabla, $nombre)) {
        echo "OK: {$nombre} ya existe.\n";
        continue;
    }

    Schema::table($tabla, function (Blueprint $table) use ($nombre, $opciones) {
        $columna = $table->decimal($nombre, 14, 2);

        if ($opciones['nullable']) {
            $columna->nullable();
        } else {
            $columna->default($opciones['default']);
        }
    });

    $agregadas[] = $nombre;
    echo "AGREGADA: {$nombre}\n";
}

echo "Esquema de Control de caja actualizado correctamente.\n";

if ($agregadas) {
    echo "Columnas nuevas: " . implode(', ', $agregadas) . "\n";
} else {
    echo "No fue necesario agregar columnas.\n";
}
