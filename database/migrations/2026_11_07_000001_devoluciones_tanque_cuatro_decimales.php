<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Migración diseñada para MySQL 8.x.');
        }

        if (! Schema::hasTable('controles_diarios_tanque')) {
            throw new RuntimeException('Falta la tabla controles_diarios_tanque.');
        }

        // Antes de crear la tabla se valida la estructura existente para impedir
        // alterar columnas con tipos no previstos.
        $actuales = [];
        foreach (DB::select('SHOW COLUMNS FROM `controles_diarios_tanque`') as $columna) {
            $actuales[$columna->Field] = strtolower($columna->Type);
        }
        foreach (['ingreso_gal', 'saldo_teorico_gal', 'diferencia_gal'] as $nombre) {
            if (! in_array($actuales[$nombre] ?? null, ['decimal(12,3)', 'decimal(13,4)'], true)) {
                throw new RuntimeException('Tipo inesperado para '.$nombre.': '.($actuales[$nombre] ?? 'ausente'));
            }
        }

        if (! Schema::hasTable('devoluciones_tanque')) {
            Schema::create('devoluciones_tanque', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('establecimiento_id');
                $table->unsignedSmallInteger('tanque_id');
                $table->unsignedBigInteger('turno_operativo_id');
                $table->date('fecha_operacion');
                $table->decimal('galones', 13, 4);
                $table->string('motivo', 255);
                $table->string('origen', 40)->default('MANUAL');
                $table->string('referencia', 120)->nullable()->unique();
                $table->unsignedBigInteger('creado_por_usuario_id')->nullable();
                $table->dateTime('creado_en');
                $table->index(['establecimiento_id', 'tanque_id', 'fecha_operacion'], 'dev_tanque_fecha_idx');
                $table->index('turno_operativo_id', 'dev_turno_idx');
            });
        }

        if ($actuales['ingreso_gal'] === 'decimal(12,3)'
            || $actuales['saldo_teorico_gal'] === 'decimal(12,3)'
            || $actuales['diferencia_gal'] === 'decimal(12,3)') {
            // 13,4 mantiene los nueve dígitos enteros del DECIMAL(12,3) anterior.
            DB::statement('ALTER TABLE `controles_diarios_tanque`
                MODIFY `ingreso_gal` DECIMAL(13,4) NOT NULL DEFAULT 0.0000,
                MODIFY `saldo_teorico_gal` DECIMAL(13,4) NULL DEFAULT NULL,
                MODIFY `diferencia_gal` DECIMAL(13,4) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        // Protección intencional: nunca borrar devoluciones ni perder decimales
        // en un rollback automático. Restaurar datos desde respaldo supervisado.
        throw new RuntimeException('Rollback de devoluciones requiere recuperación supervisada. No se borraron datos.');
    }
};
