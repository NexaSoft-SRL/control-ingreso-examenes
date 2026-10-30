<?php

declare(strict_types=1);

use App\Modules\Reportes\Http\Controllers\BitacoraExamenController;
use App\Modules\Reportes\Http\Controllers\ReportesExamenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:reportes_examenes'])
    ->prefix('api/reportes')
    ->name('reportes.')
    ->group(function (): void {
        Route::get('/', [ReportesExamenController::class, 'index'])->name('index');
        Route::get('/examenes/{examen}', [ReportesExamenController::class, 'show'])
            ->whereNumber('examen')
            ->name('examenes.show');
        Route::get('/examenes/{examen}/incidencias', [ReportesExamenController::class, 'incidencias'])
            ->middleware('permiso:seguimiento_vivo')
            ->whereNumber('examen')
            ->name('examenes.incidencias');
        Route::get('/examenes/{examen}/descargas/{tipo}/{formato}', [
            ReportesExamenController::class,
            'descargar',
        ])->whereNumber('examen')
            ->whereIn('tipo', ['asistencia', 'ausentes', 'incidencias'])
            ->whereIn('formato', ['pdf', 'xlsx'])
            ->name('examenes.descargas');
    });

Route::middleware(['auth', 'permiso:reportes_examenes|reportes_universidad'])
    ->prefix('api/reportes')
    ->name('reportes.bitacora.')
    ->group(function (): void {
        Route::get('/bitacora/examenes', [BitacoraExamenController::class, 'examenes'])
            ->name('examenes');
        Route::get('/examenes/{examen}/bitacora', [BitacoraExamenController::class, 'index'])
            ->whereNumber('examen')
            ->name('index');
        Route::get('/examenes/{examen}/bitacora/descargas/{formato}', [
            BitacoraExamenController::class,
            'descargar',
        ])->whereNumber('examen')
            ->whereIn('formato', ['pdf', 'xlsx'])
            ->name('descargas');
    });
