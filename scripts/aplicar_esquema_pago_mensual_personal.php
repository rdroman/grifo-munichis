<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

$base = dirname(__DIR__);

require $base . '/vendor/autoload.php';

$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tabla = 'ajustes_mensuales_trabajador';

if (!Schema::hasTable($tabla)) {
    fwrite(STDERR, "ERROR: No existe la tabla {$tabla}.\n");
    exit(1);
}

if (!Schema::hasColumn($tabla, 'estado_pago')) {
    Schema::table($tabla, function (Blueprint $table) {
        $table->string('estado_pago', 30)->default('PENDIENTE');
    });

    echo "AGREGADA: estado_pago\n";
} else {
    echo "OK: estado_pago ya existe.\n";
}

if (!Schema::hasColumn($tabla, 'pagado_en')) {
    Schema::table($tabla, function (Blueprint $table) {
        $table->dateTime('pagado_en')->nullable();
    });

    echo "AGREGADA: pagado_en\n";
} else {
    echo "OK: pagado_en ya existe.\n";
}

echo "Esquema de pago mensual del personal actualizado correctamente.\n";
