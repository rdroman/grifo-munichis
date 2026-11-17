<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('resumen_financiero_turno', 'efectivo_administrador')) {
            Schema::table('resumen_financiero_turno', function (Blueprint $table) {
                $table->decimal('efectivo_administrador', 14, 2)->nullable()->after('efectivo_entregado');
            });
        }

        if (! Schema::hasColumn('resumen_financiero_turno', 'usuario_validador_efectivo_id')) {
            Schema::table('resumen_financiero_turno', function (Blueprint $table) {
                $table->unsignedBigInteger('usuario_validador_efectivo_id')->nullable()->after('efectivo_administrador');
            });
        }

        if (! Schema::hasColumn('resumen_financiero_turno', 'validado_efectivo_en')) {
            Schema::table('resumen_financiero_turno', function (Blueprint $table) {
                $table->timestamp('validado_efectivo_en')->nullable()->after('usuario_validador_efectivo_id');
            });
        }

        if (! Schema::hasColumn('resumen_financiero_turno', 'observacion_validacion_efectivo')) {
            Schema::table('resumen_financiero_turno', function (Blueprint $table) {
                $table->string('observacion_validacion_efectivo', 255)->nullable()->after('validado_efectivo_en');
            });
        }

        try {
            Schema::table('resumen_financiero_turno', function (Blueprint $table) {
                $table->foreign(
                    'usuario_validador_efectivo_id',
                    'fk_resumen_financiero_validador_efectivo'
                )->references('id')->on('usuarios')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // La columna queda operativa si la FK ya existe en una base heredada.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('resumen_financiero_turno', function (Blueprint $table) {
                $table->dropForeign('fk_resumen_financiero_validador_efectivo');
            });
        } catch (\Throwable $e) {
            // Compatibilidad con instalaciones donde no llegó a crearse la FK.
        }

        foreach ([
            'observacion_validacion_efectivo',
            'validado_efectivo_en',
            'usuario_validador_efectivo_id',
            'efectivo_administrador',
        ] as $columna) {
            if (Schema::hasColumn('resumen_financiero_turno', $columna)) {
                Schema::table('resumen_financiero_turno', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }
};
