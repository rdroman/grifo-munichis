<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('reinicios_totalizador')
            || Schema::hasTable('reinicios_totalizador_detalle')
        ) {
            throw new \RuntimeException(
                'Ya existe una tabla de reinicio de totalizadores. '
                . 'La migración se canceló para no sobrescribir estructuras existentes.'
            );
        }

        try {
            Schema::create('reinicios_totalizador', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('establecimiento_id');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->unsignedBigInteger('turno_anterior_id')->nullable();
                $table->unsignedBigInteger('turno_operativo_id')->nullable();
                $table->string('estado', 20)->default('PENDIENTE');
                $table->string('motivo', 500);
                $table->dateTime('confirmado_en');
                $table->dateTime('aplicado_en')->nullable();
                $table->dateTime('creado_en');

                $table->index(
                    ['establecimiento_id', 'estado'],
                    'idx_reinicio_establecimiento_estado'
                );
                $table->index(
                    'turno_anterior_id',
                    'idx_reinicio_turno_anterior'
                );
                $table->index(
                    'turno_operativo_id',
                    'idx_reinicio_turno_aplicado'
                );
            });

            Schema::create('reinicios_totalizador_detalle', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('reinicio_totalizador_id');
                $table->unsignedBigInteger('producto_id');
                $table->unsignedBigInteger('manguera_id');
                $table->decimal('lectura_anterior', 15, 3);
                $table->decimal('lectura_base', 15, 3)->default(0);
                $table->dateTime('creado_en');

                $table->unique(
                    ['reinicio_totalizador_id', 'manguera_id'],
                    'uq_reinicio_totalizador_manguera'
                );
                $table->index(
                    ['reinicio_totalizador_id', 'producto_id'],
                    'idx_reinicio_totalizador_producto'
                );
            });
        } catch (\Throwable $e) {
            Schema::dropIfExists('reinicios_totalizador_detalle');
            Schema::dropIfExists('reinicios_totalizador');

            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reinicios_totalizador_detalle');
        Schema::dropIfExists('reinicios_totalizador');
    }
};
