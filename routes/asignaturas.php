<?php

declare(strict_types=1);

use App\Modules\Examenes\Http\Controllers\AsignaturaController;
use App\Modules\Examenes\Http\Controllers\DocenteController;
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

        // El alta del docente se hace desde Usuarios y roles: ese es el
        // permiso que corresponde, no el de asignaturas.
        Route::post(
            '/docentes',
            [DocenteController::class, 'store']
        )
            ->middleware('permiso:usuarios_roles')
            ->name('docentes.store');
    });
