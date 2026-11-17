<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('competencia_capturas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_jornada')->unique();
            $table->dateTime('fecha_consulta');
            $table->timestamp('registrado_en')->useCurrent();
            $table->string('origen', 20);
            $table->unsignedInteger('total_api');
            $table->unsignedInteger('establecimientos_guardados');
            $table->unsignedInteger('precios_guardados');
            $table->string('archivo_json', 255);
            $table->char('sha256', 64);
            $table->string('advertencia', 255)->nullable();
        });
        Schema::create('competencia_establecimientos_captura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('captura_id')->constrained('competencia_capturas')->cascadeOnDelete();
            $table->string('codigo_osinergmin', 50);
            $table->string('razon_social', 180);
            $table->string('direccion', 255)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 11, 7)->nullable();
            $table->boolean('es_munichis')->default(false);
            $table->unique(['captura_id', 'codigo_osinergmin'], 'comp_est_captura_codigo_uk');
        });
        Schema::create('competencia_precios_captura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_captura_id')->constrained('competencia_establecimientos_captura')->cascadeOnDelete();
            $table->string('codigo_producto', 50);
            $table->decimal('precio', 12, 4);
            $table->unique(['establecimiento_captura_id', 'codigo_producto'], 'comp_prec_est_prod_uk');
            $table->index('codigo_producto');
        });
        Schema::create('competencia_preferencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_osinergmin', 50)->unique();
            $table->string('categoria', 20)->default('AUTO');
            $table->timestamp('actualizado_en')->useCurrent();
        });
        Schema::create('competencia_intentos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_jornada');
            $table->timestamp('ejecutado_en')->useCurrent();
            $table->string('origen', 20);
            $table->string('estado', 20);
            $table->unsignedSmallInteger('codigo_http')->nullable();
            $table->string('detalle', 255)->nullable();
            $table->index(['fecha_jornada', 'ejecutado_en']);
        });
    }

    public function down(): void
    {
        // ADVERTENCIA: revertir esta migración ELIMINA el historial de capturas.
        Schema::dropIfExists('competencia_intentos');
        Schema::dropIfExists('competencia_preferencias');
        Schema::dropIfExists('competencia_precios_captura');
        Schema::dropIfExists('competencia_establecimientos_captura');
        Schema::dropIfExists('competencia_capturas');
    }
};
