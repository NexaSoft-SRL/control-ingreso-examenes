<?php

declare(strict_types=1);

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
