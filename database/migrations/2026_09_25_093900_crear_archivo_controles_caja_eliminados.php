<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('controles_diarios_caja_eliminados')) return;
        Schema::create('controles_diarios_caja_eliminados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('control_original_id')->index();
            $table->unsignedBigInteger('establecimiento_id')->index();
            $table->date('fecha_control')->nullable()->index();
            $table->longText('registro_json');
            $table->string('motivo_eliminacion',500);
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->timestamp('eliminado_en')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controles_diarios_caja_eliminados');
    }
};
