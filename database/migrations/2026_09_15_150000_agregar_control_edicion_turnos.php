<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos_operativos', function (Blueprint $table) {
            if (! Schema::hasColumn('turnos_operativos', 'ediciones_operador')) {
                $table->unsignedTinyInteger('ediciones_operador')
                    ->default(0)
                    ->after('observaciones');
            }

            if (! Schema::hasColumn('turnos_operativos', 'editado_operador_en')) {
                $table->timestamp('editado_operador_en')
                    ->nullable()
                    ->after('ediciones_operador');
            }

            if (! Schema::hasColumn('turnos_operativos', 'usuario_ultima_edicion_id')) {
                $table->unsignedBigInteger('usuario_ultima_edicion_id')
                    ->nullable()
                    ->after('editado_operador_en');
            }

            if (! Schema::hasColumn('turnos_operativos', 'actualizado_en')) {
                $table->timestamp('actualizado_en')
                    ->nullable()
                    ->after('usuario_ultima_edicion_id');
            }
        });

        Schema::table('turnos_operativos', function (Blueprint $table) {
            if (Schema::hasColumn('turnos_operativos', 'usuario_ultima_edicion_id')) {
                try {
                    $table->foreign(
                        'usuario_ultima_edicion_id',
                        'fk_turnos_operativos_usuario_ultima_edicion'
                    )
                        ->references('id')
                        ->on('usuarios')
                        ->nullOnDelete();
                } catch (\Throwable $e) {
                    // Si la llave ya existe por una ejecución parcial, no se duplica.
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('turnos_operativos', function (Blueprint $table) {
            if (Schema::hasColumn('turnos_operativos', 'usuario_ultima_edicion_id')) {
                try {
                    $table->dropForeign('fk_turnos_operativos_usuario_ultima_edicion');
                } catch (\Throwable $e) {
                    // Permite revertir aun si la FK no llegó a crearse.
                }
            }
        });

        Schema::table('turnos_operativos', function (Blueprint $table) {
            $columnas = [];

            foreach ([
                'ediciones_operador',
                'editado_operador_en',
                'usuario_ultima_edicion_id',
                'actualizado_en',
            ] as $columna) {
                if (Schema::hasColumn('turnos_operativos', $columna)) {
                    $columnas[] = $columna;
                }
            }

            if ($columnas) {
                $table->dropColumn($columnas);
            }
        });
    }
};
