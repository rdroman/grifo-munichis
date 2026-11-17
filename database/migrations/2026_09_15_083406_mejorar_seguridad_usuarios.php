<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración.
     */
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('trabajador_id')
                ->nullable()
                ->after('establecimiento_id');

            $table->timestamp('actualizado_en')
                ->nullable()
                ->after('creado_en');

            $table->timestamp('ultimo_acceso_en')
                ->nullable()
                ->after('actualizado_en');

            $table->unique(
                'trabajador_id',
                'uq_usuarios_trabajador_id'
            );

            $table->foreign(
                'trabajador_id',
                'fk_usuarios_trabajador_id'
            )
                ->references('id')
                ->on('trabajadores')
                ->nullOnDelete();
        });

        DB::statement("
            ALTER TABLE bitacora_auditoria
            MODIFY accion ENUM(
                'CREAR',
                'ACTUALIZAR',
                'ELIMINAR',
                'INICIAR_SESION',
                'CERRAR_SESION',
                'EXPORTAR',
                'IMPORTAR'
            ) NOT NULL
        ");
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign('fk_usuarios_trabajador_id');
            $table->dropUnique('uq_usuarios_trabajador_id');

            $table->dropColumn([
                'trabajador_id',
                'actualizado_en',
                'ultimo_acceso_en',
            ]);
        });

        /*
         * No reducimos nuevamente el ENUM de bitacora_auditoria.
         *
         * Si ya existen registros CERRAR_SESION, eliminar ese valor
         * durante un rollback podría provocar pérdida o corrupción
         * de información histórica de auditoría.
         */
    }
};