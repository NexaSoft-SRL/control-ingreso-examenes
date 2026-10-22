<?php

declare(strict_types=1);

use App\Modules\Monitoreo\Http\Controllers\EnCursoController;
use Illuminate\Support\Facades\Route;

// HU-22: el examen en curso, aula por aula (Monitoreo). Es del docente del
// examen, la misma cuenta que lo ve en «Exámenes»: pide ese permiso y,
// ademas, que el examen sea suyo. Solo lectura, salvo atender una incidencia.
Route::prefix('api/en-curso')
    ->middleware(['auth', 'permiso:examenes'])
    ->group(function (): void {
        Route::get('/', [EnCursoController::class, 'index'])
            ->name('en-curso.index');

        Route::get('/{examen}', [EnCursoController::class, 'show'])
            ->whereNumber('examen')
            ->name('en-curso.show');

        Route::patch('/{examen}/incidencias/{incidencia}/atendida', [EnCursoController::class, 'atender'])
            ->whereNumber('examen')
            ->whereNumber('incidencia')
            ->name('en-curso.atender');
    });
