<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('controles_diarios_caja')) {
            throw new \RuntimeException('No existe la tabla controles_diarios_caja.');
        }

        if (!Schema::hasColumn('controles_diarios_caja', 'otros_ingresos_caja')) {
            Schema::table('controles_diarios_caja', function (Blueprint $table) {
                $table->decimal('otros_ingresos_caja', 14, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('controles_diarios_caja') && Schema::hasColumn('controles_diarios_caja', 'otros_ingresos_caja')) {
            Schema::table('controles_diarios_caja', function (Blueprint $table) {
                $table->dropColumn('otros_ingresos_caja');
            });
        }
    }
};
