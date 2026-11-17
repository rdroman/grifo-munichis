<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columnas = [
            'telefono' => fn (Blueprint $tabla) => $tabla->string('telefono', 16)->nullable(),
            'munichis_totp_secreto' => fn (Blueprint $tabla) => $tabla->text('munichis_totp_secreto')->nullable(),
            'munichis_totp_ultimo_paso' => fn (Blueprint $tabla) => $tabla->unsignedBigInteger('munichis_totp_ultimo_paso')->nullable(),
            'munichis_totp_recuperacion' => fn (Blueprint $tabla) => $tabla->text('munichis_totp_recuperacion')->nullable(),
        ];

        $faltantes = array_diff_key($columnas, array_flip(Schema::getColumnListing('usuarios')));
        if ($faltantes !== []) {
            Schema::table('usuarios', function (Blueprint $tabla) use ($faltantes) {
                foreach ($faltantes as $agregar) {
                    $agregar($tabla);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->dropColumn(['telefono', 'munichis_totp_secreto', 'munichis_totp_ultimo_paso', 'munichis_totp_recuperacion']);
        });
    }
};
