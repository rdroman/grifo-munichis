<?php

use App\Http\Controllers\ControladorAutenticacion;
use App\Http\Controllers\ControladorIniciosSesionMunichis;
use App\Http\Controllers\ControladorSeguridadMunichis;
use App\Http\Controllers\ControladorUsuarios;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->prefix('login')->name('seguridad-munichis.')->group(function () {
    Route::get('verificacion', [ControladorAutenticacion::class, 'mostrarToken'])->name('token');
    Route::post('verificacion', [ControladorAutenticacion::class, 'validarToken'])->middleware('throttle:15,1,munichis-token-ip:')->name('token.validar');
    Route::post('verificacion/cancelar', [ControladorAutenticacion::class, 'cancelarToken'])->name('token.cancelar');
});

Route::middleware('auth')->name('seguridad-munichis.')->group(function () {
    Route::get('mi-cuenta/seguridad', [ControladorSeguridadMunichis::class, 'indice'])->name('cuenta');
    Route::post('mi-cuenta/seguridad/clave', [ControladorSeguridadMunichis::class, 'cambiarClave'])->middleware('throttle:6,1,munichis-clave:')->name('clave');
    Route::post('mi-cuenta/seguridad/token/preparar', [ControladorSeguridadMunichis::class, 'prepararToken'])->middleware('throttle:6,1,munichis-token-alta:')->name('token.preparar');
    Route::post('mi-cuenta/seguridad/token/activar', [ControladorSeguridadMunichis::class, 'activarToken'])->middleware('throttle:6,1,munichis-token-activar:')->name('token.activar');
    Route::post('mi-cuenta/seguridad/token/desactivar', [ControladorSeguridadMunichis::class, 'desactivarToken'])->middleware('throttle:6,1,munichis-token-desactivar:')->name('token.desactivar');
    Route::post('configuracion/usuarios/{usuario}/restablecer-clave', [ControladorUsuarios::class, 'restablecerClave'])
        ->whereNumber('usuario')->middleware('throttle:6,1,munichis-restablecer:')->name('restablecer');
    Route::get('auditoria/inicios-sesion', [ControladorIniciosSesionMunichis::class, 'indice'])->name('inicios');
});
