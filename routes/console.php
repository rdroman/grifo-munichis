<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// COMPETENCIA_API_MUNICHIS_V1_INICIO
Artisan::command('competencia:capturar', function () {
    try {
        $r = app(\App\Services\ServicioCapturasCompetencia::class)->capturarApi();
        $this->info(json_encode($r, JSON_UNESCAPED_UNICODE));
        return 0;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());
        return 1;
    }
})->purpose('Capturar los precios de Facilito en Yurimaguas (miércoles y viernes, 20:00).');
// COMPETENCIA_API_MUNICHIS_V1_FIN
