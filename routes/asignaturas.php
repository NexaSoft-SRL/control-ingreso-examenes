<?php

declare(strict_types=1);

use App\Modules\Examenes\Http\Controllers\AsignaturaController;
use App\Modules\Examenes\Http\Controllers\DocenteController;
use App\Modules\Examenes\Http\Controllers\ExamenController;
use App\Modules\Examenes\Http\Controllers\NormaExamenController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->middleware(['auth', 'permiso:asignaturas_ambientes'])
    ->group(function (): void {
        Route::get(
            '/asignaturas',
            [AsignaturaController::class, 'index']
        )->name('asignaturas.index');

        Route::post(
            '/asignaturas',
            [AsignaturaController::class, 'store']
        )->name('asignaturas.store');

        Route::put(
            '/asignaturas/{asignatura}',
            [AsignaturaController::class, 'update']
        )
            ->whereNumber('asignatura')
            ->name('asignaturas.update');

        Route::delete(
            '/asignaturas/{asignatura}',
            [AsignaturaController::class, 'destroy']
        )
            ->whereNumber('asignatura')
            ->name('asignaturas.destroy');

        Route::get(
            '/docentes',
            [DocenteController::class, 'index']
        )->name('docentes.index');
    });

Route::prefix('api')
    ->middleware(['auth', 'permiso:examenes_normas'])
    ->group(function (): void {
        Route::get(
            '/examenes/estudiantes',
            [ExamenController::class, 'estudiantes']
        )->name('examenes.estudiantes.index');

        Route::get(
            '/examenes',
            [ExamenController::class, 'index']
        )->name('examenes.index');

        Route::get(
            '/examenes/{examen}/asignaciones',
            [ExamenController::class, 'asignaciones']
        )
            ->whereNumber('examen')
            ->name('examenes.asignaciones.index');

        Route::post(
            '/examenes/{examen}/asignaciones',
            [ExamenController::class, 'asignarEstudiantes']
        )
            ->whereNumber('examen')
            ->name('examenes.asignaciones.store');

        Route::delete(
            '/examenes/{examen}/asignaciones',
            [ExamenController::class, 'quitarEstudiante']
        )
            ->whereNumber('examen')
            ->name('examenes.asignaciones.destroy');

        Route::get(
            '/examenes/{examen}/normas',
            [NormaExamenController::class, 'index']
        )
            ->whereNumber('examen')
            ->name('examenes.normas.index');

        Route::post(
            '/examenes/{examen}/normas',
            [NormaExamenController::class, 'store']
        )
            ->whereNumber('examen')
            ->name('examenes.normas.store');

        Route::delete(
            '/examenes/{examen}/normas/{norma}',
            [NormaExamenController::class, 'destroy']
        )
            ->whereNumber('examen')
            ->whereNumber('norma')
            ->name('examenes.normas.destroy');
    });

Route::prefix('api/control')
    ->middleware(['auth', 'permiso:punto_control'])
    ->group(function (): void {
        Route::get(
            '/examenes',
            [ExamenController::class, 'controlIndex']
        )->name('control.examenes.index');

        Route::get(
            '/estudiantes',
            [ExamenController::class, 'controlEstudiantes']
        )->name('control.estudiantes.index');

        Route::get(
            '/examenes/{examen}/estudiantes/{estudiante}/normas',
            [NormaExamenController::class, 'paraEstudiante']
        )
            ->whereNumber('examen')
            ->whereNumber('estudiante')
            ->name('control.examenes.normas.estudiante');
    });

// El alta del docente se hace desde la pantalla de usuarios y roles, no
// desde la de asignaturas: por eso va en su propio grupo, con su permiso.
// Dentro del grupo anterior exigiria los dos permisos a la vez.
Route::prefix('api')
    ->middleware(['auth', 'permiso:usuarios_roles'])
    ->group(function (): void {
        Route::post(
            '/docentes',
            [DocenteController::class, 'store']
        )->name('docentes.store');
    });
