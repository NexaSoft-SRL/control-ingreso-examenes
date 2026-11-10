<?php

declare(strict_types=1);

use App\Modules\Ingreso\Http\Controllers\AccesoAulaController;
use App\Modules\Ingreso\Http\Controllers\CodigoQrController;
use App\Modules\Ingreso\Http\Controllers\ControlDelExamenController;
use App\Modules\Ingreso\Http\Controllers\IncidenciaController;
use App\Modules\Ingreso\Http\Controllers\IncidenciasComunesController;
use App\Modules\Ingreso\Http\Controllers\PuertaController;
use Illuminate\Support\Facades\Route;

// Codigos QR, puerta, incidencias y acceso con codigo de aula (Ingreso).

// Entrar a la puerta de un aula con su codigo, sin cuenta. Cinco codigos
// equivocados cada cinco minutos por direccion (los cuenta el controlador).
Route::post('/api/auth/codigo-aula', [AccesoAulaController::class, 'abrir'])
    ->name('auth.codigo-aula');

Route::get('/api/auth/codigo-aula', [AccesoAulaController::class, 'estado'])
    ->name('auth.codigo-aula.estado');

Route::delete('/api/auth/codigo-aula', [AccesoAulaController::class, 'cerrar'])
    ->name('auth.codigo-aula.cerrar');

Route::middleware(['auth', 'permiso:codigos_qr'])
    ->prefix('api/examenes/{examen}/codigos-qr')
    ->whereNumber(['examen', 'estudiante'])
    ->name('codigos-qr.')
    ->group(function (): void {
        Route::get('/', [CodigoQrController::class, 'index'])->name('index');
        Route::post('/', [CodigoQrController::class, 'store'])->name('emitir');
        Route::post('/envios', [CodigoQrController::class, 'enviar'])->name('enviar');
        Route::post('/avisos', [CodigoQrController::class, 'avisar'])->name('avisar');
        Route::get('/hoja', [CodigoQrController::class, 'hoja'])->name('hoja');
        Route::get('/{estudiante}', [CodigoQrController::class, 'show'])->name('credencial');
        Route::post('/{estudiante}/envio', [CodigoQrController::class, 'reenviar'])->name('reenviar');
    });

// La puerta admite a una cuenta con `punto_control` o a una sesion con
// codigo de aula: por eso no lleva `auth`.
Route::middleware('puesto')
    ->prefix('api/puerta')
    ->whereNumber(['aula', 'ingreso'])
    ->name('puerta.')
    ->group(function (): void {
        Route::get('/aulas', [PuertaController::class, 'aulas'])->name('aulas');
        Route::get('/aulas/{aula}', [PuertaController::class, 'show'])->name('aula');
        Route::get('/aulas/{aula}/edificios', [PuertaController::class, 'edificios'])->name('edificios');
        Route::post('/aulas/{aula}/verificaciones', [PuertaController::class, 'verificar'])->name('verificar');
        Route::post('/aulas/{aula}/excepciones', [PuertaController::class, 'excepcion'])->name('excepcion');
        Route::post('/ingresos/{ingreso}/expulsion', [PuertaController::class, 'expulsion'])->name('expulsion');
    });

Route::get('/api/examenes/{examen}/incidencias', [IncidenciaController::class, 'index'])
    ->middleware(['auth', 'permiso:seguimiento_vivo'])
    ->whereNumber('examen')
    ->name('incidencias.index');

// Lo que se ofrece al registrar: la lista de incidencias comunes del docente.
Route::get('/api/examenes/{examen}/incidencias/catalogo', [IncidenciasComunesController::class, 'catalogo'])
    ->middleware('puesto:seguimiento_vivo')
    ->whereNumber('examen')
    ->name('incidencias.catalogo');

// La lista propia de incidencias comunes, con la gravedad de cada una.
Route::middleware(['auth', 'permiso:seguimiento_vivo'])->group(function (): void {
    Route::get('/api/incidencias-comunes', [IncidenciasComunesController::class, 'index'])->name('incidencias-comunes.index');
    Route::put('/api/incidencias-comunes', [IncidenciasComunesController::class, 'guardar'])->name('incidencias-comunes.guardar');
    Route::delete('/api/incidencias-comunes/{id}', [IncidenciasComunesController::class, 'quitar'])
        ->whereNumber('id')
        ->name('incidencias-comunes.quitar');
});

// Reporta quien sigue el examen o quien esta en la puerta.
Route::post('/api/examenes/{examen}/incidencias', [IncidenciaController::class, 'store'])
    ->middleware('puesto:seguimiento_vivo')
    ->whereNumber('examen')
    ->name('incidencias.registrar');

Route::post('/api/incidencias/{incidencia}/atencion', [IncidenciaController::class, 'atender'])
    ->middleware(['auth', 'permiso:seguimiento_vivo'])
    ->whereNumber('incidencia')
    ->name('incidencias.atender');

Route::post('/api/incidencias/{incidencia}/correccion', [IncidenciaController::class, 'corregir'])
    ->middleware(['auth', 'permiso:seguimiento_vivo'])
    ->whereNumber('incidencia')
    ->name('incidencias.corregir');

Route::get('/api/incidencias/{incidencia}/evidencias/{numero}', [IncidenciaController::class, 'evidencia'])
    ->middleware(['auth', 'permiso:seguimiento_vivo'])
    ->whereNumber(['incidencia', 'numero'])
    ->name('incidencias.evidencias');

Route::get('/api/incidencias/{incidencia}/video', [IncidenciaController::class, 'video'])
    ->middleware(['auth', 'permiso:seguimiento_vivo'])
    ->whereNumber('incidencia')
    ->name('incidencias.video');

Route::get('/api/incidencias/{incidencia}/evidencia', [IncidenciaController::class, 'evidencia'])
    ->middleware(['auth', 'permiso:seguimiento_vivo'])
    ->whereNumber('incidencia')
    ->name('incidencias.evidencia');

// El panel en vivo del docente: quien controla cada aula, la autorizacion
// de los accesos con codigo y los cambios de aula durante el examen.
Route::middleware(['auth', 'permiso:examenes'])
    ->prefix('api/examenes/{examen}')
    ->whereNumber(['examen', 'acceso'])
    ->name('control.')
    ->group(function (): void {
        Route::get('/control', [ControlDelExamenController::class, 'show'])->name('show');
        Route::post('/accesos/{acceso}/{accion}', [ControlDelExamenController::class, 'resolver'])
            ->whereIn('accion', ['autorizar', 'rechazar', 'cortar', 'trasladar'])
            ->name('acceso');
        Route::post('/movimientos', [ControlDelExamenController::class, 'mover'])->name('mover');
    });
