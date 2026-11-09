<?php

declare(strict_types=1);

use App\Modules\Monitoreo\Http\Controllers\AsistenciaController;
use App\Modules\Monitoreo\Http\Controllers\EnCursoController;
use Illuminate\Support\Facades\Route;

// En curso y asistencia por aula (Monitoreo). Solo lectura: el seguimiento
// no tiene ninguna ruta que escriba.
Route::middleware(['auth', 'permiso:seguimiento_vivo'])->group(function (): void {
    Route::get(
        '/api/en-curso',
        [EnCursoController::class, 'index'],
    )->name('monitoreo.en_curso.index');

    Route::get(
        '/api/examenes/{examen}/en-curso',
        [EnCursoController::class, 'show'],
    )->whereNumber('examen')->name('monitoreo.en_curso.show');

    Route::get(
        '/api/examenes/{examen}/aulas/{aula}/asistencia',
        AsistenciaController::class,
    )->whereNumber(['examen', 'aula'])->name('monitoreo.asistencia');
});
