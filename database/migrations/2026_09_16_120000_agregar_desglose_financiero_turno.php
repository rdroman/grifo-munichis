<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablaProducto = 'totales_producto_turno';

        if (Schema::hasTable($tablaProducto)) {
            $faltan = [
                'galones_credito' => ! Schema::hasColumn($tablaProducto, 'galones_credito'),
                'galones_descuento' => ! Schema::hasColumn($tablaProducto, 'galones_descuento'),
                'galones_vendidos' => ! Schema::hasColumn($tablaProducto, 'galones_vendidos'),
                'precio_vigente' => ! Schema::hasColumn($tablaProducto, 'precio_vigente'),
                'importe_creditos' => ! Schema::hasColumn($tablaProducto, 'importe_creditos'),
                'importe_descuentos' => ! Schema::hasColumn($tablaProducto, 'importe_descuentos'),
                'importe_neto' => ! Schema::hasColumn($tablaProducto, 'importe_neto'),
            ];

            Schema::table($tablaProducto, function (Blueprint $table) use ($faltan) {
                if ($faltan['galones_credito']) $table->decimal('galones_credito', 12, 3)->default(0);
                if ($faltan['galones_descuento']) $table->decimal('galones_descuento', 12, 3)->default(0);
                if ($faltan['galones_vendidos']) $table->decimal('galones_vendidos', 12, 3)->default(0);
                if ($faltan['precio_vigente']) $table->decimal('precio_vigente', 12, 4)->nullable();
                if ($faltan['importe_creditos']) $table->decimal('importe_creditos', 14, 2)->default(0);
                if ($faltan['importe_descuentos']) $table->decimal('importe_descuentos', 14, 2)->default(0);
                if ($faltan['importe_neto']) $table->decimal('importe_neto', 14, 2)->default(0);
            });
        }

        $tablaFinanciera = 'resumen_financiero_turno';

        if (Schema::hasTable($tablaFinanciera)) {
            $faltan = [
                'importe_descuentos' => ! Schema::hasColumn($tablaFinanciera, 'importe_descuentos'),
                'importe_creditos' => ! Schema::hasColumn($tablaFinanciera, 'importe_creditos'),
                'monto_neto_combustible' => ! Schema::hasColumn($tablaFinanciera, 'monto_neto_combustible'),
            ];

            Schema::table($tablaFinanciera, function (Blueprint $table) use ($faltan) {
                if ($faltan['importe_descuentos']) $table->decimal('importe_descuentos', 14, 2)->default(0);
                if ($faltan['importe_creditos']) $table->decimal('importe_creditos', 14, 2)->default(0);
                if ($faltan['monto_neto_combustible']) $table->decimal('monto_neto_combustible', 14, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'monto_neto_combustible',
            'importe_creditos',
            'importe_descuentos',
        ] as $columna) {
            if (Schema::hasTable('resumen_financiero_turno') && Schema::hasColumn('resumen_financiero_turno', $columna)) {
                Schema::table('resumen_financiero_turno', fn (Blueprint $table) => $table->dropColumn($columna));
            }
        }

        foreach ([
            'importe_neto',
            'importe_descuentos',
            'importe_creditos',
            'precio_vigente',
            'galones_vendidos',
            'galones_descuento',
            'galones_credito',
        ] as $columna) {
            if (Schema::hasTable('totales_producto_turno') && Schema::hasColumn('totales_producto_turno', $columna)) {
                Schema::table('totales_producto_turno', fn (Blueprint $table) => $table->dropColumn($columna));
            }
        }
    }
};
