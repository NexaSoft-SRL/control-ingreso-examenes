<?php

declare(strict_types=1);

use App\Modules\Ingreso\Http\Controllers\ContextoPuntoControlController;
use App\Modules\Ingreso\Http\Controllers\ExpulsarIngresoEnPuertaController;
use App\Modules\Ingreso\Http\Controllers\IdentificarEnPuertaController;
use App\Modules\Ingreso\Http\Controllers\IncidenciasEnPuertaController;
use App\Modules\Ingreso\Http\Controllers\IngresosRecientesEnPuertaController;
use App\Modules\Ingreso\Http\Controllers\ListarIncidenciasManualesPuertaController;
use App\Modules\Ingreso\Http\Controllers\PuntoControlController;
use App\Modules\Ingreso\Http\Controllers\RegistrarIncidenciaManualPuertaController;
use App\Modules\Ingreso\Http\Controllers\RegistrarIngresoEnPuertaController;
use App\Modules\Ingreso\Http\Controllers\SeguimientoAulaController;
use App\Modules\Ingreso\Http\Middleware\VerificarAccesoPuntoControl;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:punto_control'])
    ->get(
        'api/aulas/{aula}/punto-control/incidencias',
        IncidenciasEnPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.incidencias');

Route::middleware(['auth', 'permiso:punto_control'])
    ->get(
        'api/aulas/{aula}/punto-control/ingresos',
        IngresosRecientesEnPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.recientes');

Route::middleware(['auth', 'permiso:punto_control'])
    ->post(
        'api/aulas/{aula}/punto-control/identificar',
        IdentificarEnPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.identificar');

Route::middleware(['auth', 'permiso:punto_control'])
    ->post(
        'api/aulas/{aula}/punto-control/ingresos',
        RegistrarIngresoEnPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.registrar');

Route::middleware(['auth', 'permiso:punto_control'])
    ->post('api/aulas/{aula}/punto-control/ingresos/{ingreso}/expulsion', ExpulsarIngresoEnPuertaController::class)
    ->whereNumber('aula')
    ->whereNumber('ingreso')
    ->name('ingreso.puerta.expulsar');

Route::middleware(['auth', 'permiso:punto_control'])
    ->post(
        'api/aulas/{aula}/punto-control/incidencias-manuales',
        RegistrarIncidenciaManualPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.incidencia-manual');

Route::middleware(['auth', 'permiso:punto_control'])
    ->get(
        'api/aulas/{aula}/punto-control/incidencias-manuales',
        ListarIncidenciasManualesPuertaController::class,
    )
    ->whereNumber('aula')
    ->name('ingreso.puerta.incidencias-manuales');

Route::middleware(['auth', 'permiso:punto_control'])
    ->get('api/punto-control/contexto', ContextoPuntoControlController::class)
    ->name('ingreso.contexto');

Route::middleware([VerificarAccesoPuntoControl::class])
    ->prefix('api/examenes/{examen}/aulas/{aula}/punto-control')
    ->whereNumber('examen')
    ->whereNumber('aula')
    ->group(function (): void {
        Route::get('candidato', [
            PuntoControlController::class,
            'candidato',
        ])->name('ingreso.candidato');

        Route::post('ingresos', [
            PuntoControlController::class,
            'registrar',
        ])->name('ingreso.registrar');

        Route::get('ingresos', [
            PuntoControlController::class,
            'recientes',
        ])->name('ingreso.recientes');

        Route::get('incidencias', [
            PuntoControlController::class,
            'incidencias',
        ])->name('ingreso.incidencias');
    });

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

        Route::patch('incidencias/{incidencia}/atendida', [
            SeguimientoAulaController::class,
            'atenderIncidencia',
        ])->whereNumber('incidencia')
            ->name('seguimiento.atender-incidencia');
    });
