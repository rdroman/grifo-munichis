<?php
use App\Http\Controllers\ControladorCajaEliminacion;
use App\Http\Controllers\ControladorReportesMejorados;
use App\Http\Controllers\ControladorPanelSupervisor;

use App\Http\Controllers\ControladorDescuentos;
use App\Http\Controllers\ControladorAjustesPersonal;
use App\Http\Controllers\ControladorAuditoria;
use App\Http\Controllers\ControladorAutenticacion;
use App\Http\Controllers\ControladorCatalogos;
use App\Http\Controllers\ControladorCompetencia;
use App\Http\Controllers\ControladorCompetenciaApi;
use App\Http\Controllers\ControladorConfiguracion;
use App\Http\Controllers\ControladorCreditos;
use App\Http\Controllers\ControladorDiferencias;
use App\Http\Controllers\ControladorCrud;
use App\Http\Controllers\ControladorCaja;
use App\Http\Controllers\ControladorMovimientos;
use App\Http\Controllers\ControladorPanel;
use App\Http\Controllers\ControladorPrecios;
use App\Http\Controllers\ControladorReportes;
use App\Http\Controllers\ControladorTanques;
use App\Http\Controllers\ControladorTrabajadores;
use App\Http\Controllers\ControladorTurnos;
use App\Http\Controllers\ControladorReinicioTotalizadores;
use App\Http\Controllers\ControladorUsuarios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('panel') : redirect()->route('login');
})->name('inicio');

Route::middleware('guest')->group(function () {
    Route::get('/login', [ControladorAutenticacion::class, 'mostrarLogin'])->name('login');
    Route::post('/login', [ControladorAutenticacion::class, 'iniciarSesion'])->middleware('throttle:5,1')->name('login.procesar');
});

Route::middleware('auth')->group(function () {
    Route::get('/panel', [ControladorPanel::class, 'indice'])->name('panel');

    Route::get('/movimientos', [ControladorMovimientos::class, 'indice'])->name('movimientos.indice');

    Route::prefix('turnos')->name('turnos.')->group(function () {
        Route::get('/', [ControladorTurnos::class, 'indice'])->name('indice');
        Route::get('/nuevo', [ControladorTurnos::class, 'crear'])->name('crear');
        Route::post('/', [ControladorTurnos::class, 'guardar'])->name('guardar');
        Route::post(
            '/reinicio-totalizadores',
            [ControladorReinicioTotalizadores::class, 'guardar']
        )->name('reinicio-totalizadores.guardar');
        Route::get('/{turno}/editar', [ControladorTurnos::class, 'editar'])->name('editar');
        Route::patch('/{turno}/editar', [ControladorTurnos::class, 'actualizar'])->name('actualizar');
        Route::get('/{turno}', [ControladorTurnos::class, 'mostrar'])->whereNumber('turno')->name('mostrar');
        Route::patch('/{turno}/entrega', [ControladorTurnos::class, 'registrarEntrega'])->whereNumber('turno')->name('entrega');
        Route::patch('/{turno}/correccion-efectivo', [ControladorTurnos::class, 'corregirEfectivo'])->whereNumber('turno')->name('corregir-efectivo');
        Route::patch('/{turno}/validacion-digital', [ControladorTurnos::class, 'validarDigital'])->whereNumber('turno')->name('validar');
    });

    // Route::prefix('tanques')->name('tanques.')->group(function () {
    //     Route::get('/', [ControladorTanques::class, 'indice'])->name('indice');
    //     Route::post('/control', [ControladorTanques::class, 'guardar'])->name('guardar');
    //     Route::get('/tanques/calcular',[ControladorTanques::class, 'calcular'])->name('calcular');
    // });
    Route::prefix('tanques')->name('tanques.')->group(function () {
        Route::get('/', [ControladorTanques::class, 'indice'])->name('indice');
        Route::post('/control', [ControladorTanques::class, 'guardar'])->name('guardar');
        Route::post('/devolucion', [ControladorTanques::class, 'guardarDevolucion'])->name('devolucion.guardar')->middleware('throttle:6,1');
        Route::get('calcular', [ControladorTanques::class, 'calcular'])->name('calcular');
    });

    Route::prefix('trabajadores')->name('trabajadores.')->group(function () {
        Route::get('/', [ControladorTrabajadores::class, 'indice'])->name('indice');
        Route::get('/nuevo', [ControladorTrabajadores::class, 'crear'])->name('crear');
        Route::post('/', [ControladorTrabajadores::class, 'guardar'])->name('guardar');
        Route::get('/{trabajador}/editar', [ControladorTrabajadores::class, 'editar'])->whereNumber('trabajador')->name('editar');
        Route::put('/{trabajador}', [ControladorTrabajadores::class, 'actualizar'])->whereNumber('trabajador')->name('actualizar');
        Route::patch('/{trabajador}/estado', [ControladorTrabajadores::class, 'cambiarEstado'])->whereNumber('trabajador')->name('estado');
    });

    Route::prefix('creditos')->name('creditos.')->group(function () {
        Route::get('/', [ControladorCreditos::class, 'indice'])->name('indice');
        Route::get('/nuevo', [ControladorCreditos::class, 'crear'])->name('crear');
        Route::get('/precio-vigente', [ControladorCreditos::class, 'precioVigente'])->name('precio-vigente');
        Route::post('/', [ControladorCreditos::class, 'guardar'])->name('guardar');
        Route::post('/clientes/{cliente}/pagos', [ControladorCreditos::class, 'guardarPago'])->whereNumber('cliente')->name('pago');
        Route::get('/{credito}/editar', [ControladorCreditos::class, 'editar'])->whereNumber('credito')->name('editar');
        Route::put('/{credito}', [ControladorCreditos::class, 'actualizar'])->whereNumber('credito')->name('actualizar');        Route::get('/{credito}', [ControladorCreditos::class, 'mostrar'])->whereNumber('credito')->name('mostrar');
    });

    Route::prefix('precios')->name('precios.')->group(function () {
        Route::get('/', [ControladorPrecios::class, 'indice'])->name('indice');
        Route::post('/', [ControladorPrecios::class, 'guardar'])->name('guardar');
    });

    // COMPETENCIA_API_MUNICHIS_V1_INICIO
    Route::prefix('competencia')->name('competencia.')->group(function () {
        Route::get('/', [ControladorCompetenciaApi::class, 'indice'])->name('indice');
        Route::get('/manual', [ControladorCompetencia::class, 'indice'])->name('manual');
        Route::post('/grifos', [ControladorCompetencia::class, 'guardarGrifo'])->name('grifo');
        Route::post('/precios', [ControladorCompetencia::class, 'guardarPrecio'])->name('precio');
        Route::post('/capturar', [ControladorCompetenciaApi::class, 'capturar'])->name('capturar')->middleware('throttle:2,10');
        Route::post('/importar', [ControladorCompetenciaApi::class, 'importar'])->name('importar')->middleware('throttle:3,10');
        Route::post('/preferencia', [ControladorCompetenciaApi::class, 'preferencia'])->name('preferencia');
        Route::get('/exportar', [ControladorCompetenciaApi::class, 'exportar'])->name('exportar');
    });
    // COMPETENCIA_API_MUNICHIS_V1_FIN

    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ControladorReportesMejorados::class, 'indice'])->name('indice');
        Route::get('/detalle/{tipo}', [ControladorReportesMejorados::class, 'detalle'])->name('detalle'); // PARCHE_UI_REPORTES_CAJA_20260925
        Route::get('/exportar', [ControladorReportes::class, 'exportar'])->name('exportar');
    });

    Route::get('/diferencias', [ControladorDiferencias::class, 'indice'])->name('diferencias.indice');

    Route::get('/configuracion', [ControladorConfiguracion::class, 'indice'])->name('configuracion');

    Route::prefix('configuracion/usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [ControladorUsuarios::class, 'indice'])->name('indice');
        Route::get('/nuevo', [ControladorUsuarios::class, 'crear'])->name('crear');
        Route::post('/', [ControladorUsuarios::class, 'guardar'])->name('guardar');
        Route::get('/{usuario}/editar', [ControladorUsuarios::class, 'editar'])->whereNumber('usuario')->name('editar');
        Route::put('/{usuario}', [ControladorUsuarios::class, 'actualizar'])->whereNumber('usuario')->name('actualizar');
    });

    Route::prefix('configuracion/catalogos')->name('catalogos.')->group(function () {
        Route::get('/{catalogo}', [ControladorCatalogos::class, 'indice'])->name('indice');
        Route::get('/{catalogo}/nuevo', [ControladorCatalogos::class, 'crear'])->name('crear');
        Route::post('/{catalogo}', [ControladorCatalogos::class, 'guardar'])->name('guardar');
        Route::get('/{catalogo}/{id}/editar', [ControladorCatalogos::class, 'editar'])->whereNumber('id')->name('editar');
        Route::put('/{catalogo}/{id}', [ControladorCatalogos::class, 'actualizar'])->whereNumber('id')->name('actualizar');
    });

    Route::get('/auditoria', [ControladorAuditoria::class, 'indice'])->name('auditoria');

    // [PARCHE_FINANCIERO_TURNOS] Rutas específicas de descuentos

    Route::get('/gestion/descuentos', [ControladorDescuentos::class, 'indice'])->name('descuentos.indice');

    Route::get('/gestion/descuentos/nuevo', [ControladorDescuentos::class, 'crear'])->name('descuentos.crear');

    Route::get('/gestion/descuentos/precio', [ControladorDescuentos::class, 'precio'])->name('descuentos.precio');

    Route::post('/gestion/descuentos', [ControladorDescuentos::class, 'guardar'])->name('descuentos.guardar');

    Route::get('/gestion/descuentos/{descuento}/editar', [ControladorDescuentos::class, 'editar'])->name('descuentos.editar');

    Route::put('/gestion/descuentos/{descuento}', [ControladorDescuentos::class, 'actualizar'])->name('descuentos.actualizar');



    // [PARCHE_CONTROL_CAJA_V1_1_INICIO]
    Route::get('/gestion/caja', [ControladorCaja::class, 'indice'])->name('caja.indice');
    Route::get('/gestion/caja/nuevo', [ControladorCaja::class, 'crear'])->name('caja.crear');
    Route::get('/gestion/caja/resumen', [ControladorCaja::class, 'resumen'])->name('caja.resumen');
    Route::post('/gestion/caja', [ControladorCaja::class, 'guardar'])->name('caja.guardar');
    // [PARCHE_CONTROL_CAJA_V1_1_FIN]
    // [PAGO_MENSUAL_PERSONAL_V1_1_INICIO]
    Route::get('/gestion/ajustes-personal/resumen', [ControladorAjustesPersonal::class, 'resumen'])->name('ajustes-personal.resumen');
    Route::get('/gestion/ajustes-personal', [ControladorAjustesPersonal::class, 'indice'])->name('ajustes-personal.indice');
    Route::get('/gestion/ajustes-personal/nuevo', [ControladorAjustesPersonal::class, 'crear'])->name('ajustes-personal.crear');
    Route::post('/gestion/ajustes-personal', [ControladorAjustesPersonal::class, 'guardar'])->name('ajustes-personal.guardar');
    Route::get('/gestion/ajustes-personal/{ajuste}/editar', [ControladorAjustesPersonal::class, 'editar'])->whereNumber('ajuste')->name('ajustes-personal.editar');
    Route::put('/gestion/ajustes-personal/{ajuste}', [ControladorAjustesPersonal::class, 'actualizar'])->whereNumber('ajuste')->name('ajustes-personal.actualizar');
    Route::patch('/gestion/ajustes-personal/{ajuste}/pagado', [ControladorAjustesPersonal::class, 'marcarPagado'])->whereNumber('ajuste')->name('ajustes-personal.pagado');
    Route::patch('/gestion/ajustes-personal/{ajuste}/reabrir', [ControladorAjustesPersonal::class, 'reabrir'])->whereNumber('ajuste')->name('ajustes-personal.reabrir');
    // [PAGO_MENSUAL_PERSONAL_V1_1_FIN]
    Route::get('/gestion/gastos/{id}/eliminar', [\App\Http\Controllers\ControladorGastosEliminacion::class, 'confirmar'])
        ->whereNumber('id')->name('gastos.confirmar-eliminacion');
    Route::delete('/gestion/gastos/{id}', [\App\Http\Controllers\ControladorGastosEliminacion::class, 'eliminar'])
        ->whereNumber('id')->name('gastos.eliminar');

    Route::prefix('gestion')->name('crud.')->group(function () {
        Route::get('/{modulo}', [ControladorCrud::class, 'indice'])->whereIn('modulo',['compras','gastos','clientes','descuentos','caja','ajustes-personal','pagos-turno','campanas'])->name('indice');
        Route::get('/{modulo}/nuevo', [ControladorCrud::class, 'crear'])->whereIn('modulo',['compras','gastos','clientes','descuentos','caja','ajustes-personal','pagos-turno','campanas'])->name('crear');
        Route::post('/{modulo}', [ControladorCrud::class, 'guardar'])->whereIn('modulo',['compras','gastos','clientes','descuentos','caja','ajustes-personal','pagos-turno','campanas'])->name('guardar');
        Route::get('/{modulo}/{id}/editar', [ControladorCrud::class, 'editar'])->whereIn('modulo',['compras','gastos','clientes','descuentos','caja','ajustes-personal','pagos-turno','campanas'])->whereNumber('id')->name('editar');
        Route::put('/{modulo}/{id}', [ControladorCrud::class, 'actualizar'])->whereIn('modulo',['compras','gastos','clientes','descuentos','caja','ajustes-personal','pagos-turno','campanas'])->whereNumber('id')->name('actualizar');
    });

    // Compatibilidad con enlaces de la interfaz v2 inicial.
    Route::get('/modulos/{modulo}', function (string $modulo) {
        return match ($modulo) {
            'movimientos' => redirect()->route('movimientos.indice'),
            'turnos' => redirect()->route('turnos.indice'),
            'tanques' => redirect()->route('tanques.indice'),
            'caja' => redirect()->route('caja.indice'),
            'compras','gastos','clientes','descuentos','ajustes-personal','pagos-turno','campanas' => redirect()->route('crud.indice',$modulo),
            'creditos' => redirect()->route('creditos.indice'),
            'precios' => redirect()->route('precios.indice'),
            'competencia' => redirect()->route('competencia.indice'),
            'reportes' => redirect()->route('reportes.indice'),
            'diferencias' => redirect()->route('diferencias.indice'),
            'configuracion' => redirect()->route('configuracion'),
            'auditoria' => redirect()->route('auditoria'),
            default => abort(404),
        };
    })->name('modulos.mostrar');

    Route::delete('/caja/control/{id}/eliminar', [ControladorCajaEliminacion::class, 'eliminar'])->whereNumber('id')->name('caja.eliminar'); // PARCHE_UI_REPORTES_CAJA_20260925
    Route::post('/cerrar-sesion', [ControladorAutenticacion::class, 'cerrarSesion'])->name('logout');
});

require __DIR__ . '/seguridad-munichis.php';
