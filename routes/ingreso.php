<?php

declare(strict_types=1);

use App\Modules\Ingreso\Http\Controllers\CodigoQrController;
use App\Modules\Ingreso\Http\Controllers\ContextoPuntoControlController;
use App\Modules\Ingreso\Http\Controllers\ControlDelExamenController;
use App\Modules\Ingreso\Http\Controllers\EdificiosDePuertaController;
use App\Modules\Ingreso\Http\Controllers\ExpulsarIngresoEnPuertaController;
use App\Modules\Ingreso\Http\Controllers\IdentificarEnPuertaController;
use App\Modules\Ingreso\Http\Controllers\IncidenciaController;
use App\Modules\Ingreso\Http\Controllers\IngresosRecientesEnPuertaController;
use App\Modules\Ingreso\Http\Controllers\PuertaAulaController;
use App\Modules\Ingreso\Http\Controllers\RegistrarExcepcionPuertaController;
use App\Modules\Ingreso\Http\Controllers\RegistrarIngresoEnPuertaController;
use App\Modules\Ingreso\Http\Controllers\SeguimientoAulaController;
use Illuminate\Support\Facades\Route;

// La puerta (HU-19) admite a una cuenta con `punto_control` o a quien entro
// con un codigo de aula autorizado por el docente, solo en su aula: por eso
// lleva `puesto` y no `auth`.

Route::middleware('puesto')
    ->get('api/aulas/{aula}/punto-control', PuertaAulaController::class)
    ->whereNumber('aula')
    ->name('ingreso.puerta.aula');

Route::middleware('puesto')
    ->post(
        'api/aulas/{aula}/punto-control/excepciones',
        RegistrarExcepcionPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.excepcion');

Route::middleware('puesto')
    ->get(
        'api/aulas/{aula}/punto-control/ingresos',
        IngresosRecientesEnPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.recientes');

Route::middleware('puesto')
    ->post(
        'api/aulas/{aula}/punto-control/identificar',
        IdentificarEnPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.identificar');

Route::middleware('puesto')
    ->post(
        'api/aulas/{aula}/punto-control/ingresos',
        RegistrarIngresoEnPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.registrar');

Route::middleware('puesto')
    ->post('api/aulas/{aula}/punto-control/ingresos/{ingreso}/expulsion', ExpulsarIngresoEnPuertaController::class)
    ->whereNumber('aula')
    ->whereNumber('ingreso')
    ->name('ingreso.puerta.expulsar');

Route::middleware('puesto')
    ->get('api/punto-control/contexto', ContextoPuntoControlController::class)
    ->name('ingreso.contexto');

Route::middleware('puesto')
    ->get('api/aulas/{aula}/punto-control/edificios', EdificiosDePuertaController::class)
    ->whereNumber('aula')
    ->name('ingreso.puerta.edificios');

Route::middleware(['auth', 'permiso:seguimiento_vivo'])
    ->prefix('api/seguimiento')
    ->group(function (): void {
        Route::get('aulas', [SeguimientoAulaController::class, 'aulas'])
            ->name('seguimiento.aulas');

        Route::get('examenes/{examen}/aulas/{aula}', [
            SeguimientoAulaController::class,
            'detalle',
        ])->whereNumber('examen')->whereNumber('aula')
            ->name('seguimiento.aula');
    });

// Incidencias en un solo modelo (HU-19, HU-21, HU-22). Las lista el
// docente del examen o quien atiende el aula; las reporta tambien quien
// controla la puerta con codigo de aula; el docente las atiende y corrige.
Route::get('api/examenes/{examen}/incidencias', [IncidenciaController::class, 'index'])
    ->middleware(['auth', 'permiso:seguimiento_vivo|examenes'])
    ->whereNumber('examen')
    ->name('incidencias.index');

Route::post('api/examenes/{examen}/incidencias', [IncidenciaController::class, 'store'])
    ->middleware('puesto:seguimiento_vivo,examenes')
    ->whereNumber('examen')
    ->name('incidencias.registrar');

Route::middleware(['auth', 'permiso:seguimiento_vivo|examenes'])
    ->prefix('api/incidencias/{incidencia}')
    ->whereNumber('incidencia')
    ->name('incidencias.')
    ->group(function (): void {
        Route::post('/atencion', [IncidenciaController::class, 'atender'])->name('atender');
        Route::post('/correccion', [IncidenciaController::class, 'corregir'])->name('corregir');
        Route::get('/evidencia', [IncidenciaController::class, 'evidencia'])->name('evidencia');
    });

// El panel en vivo del docente (HU-22): la autorizacion de los accesos con
// codigo de aula (HU-18).
Route::middleware(['auth', 'permiso:examenes'])
    ->prefix('api/examenes/{examen}')
    ->whereNumber(['examen', 'acceso'])
    ->name('control.')
    ->group(function (): void {
        Route::get('/control', [ControlDelExamenController::class, 'show'])->name('show');
        Route::post('/movimientos', [ControlDelExamenController::class, 'mover'])->name('mover');
        Route::post('/accesos/{acceso}/{accion}', [ControlDelExamenController::class, 'resolver'])
            ->whereIn('accion', ['autorizar', 'rechazar', 'cortar', 'trasladar'])
            ->name('acceso');
    });

// HU-20: codigos QR y avisos por correo de un examen. Ademas del permiso,
// cada accion exige ser docente del examen.
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
