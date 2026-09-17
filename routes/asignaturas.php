<?php

declare(strict_types=1);

use App\Modules\Examenes\Http\Controllers\AsignaturaController;
use App\Modules\Examenes\Http\Controllers\DocenteController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->middleware('auth')
    ->group(function (): void {
        Route::get(
            '/asignaturas',
            [AsignaturaController::class, 'index']
        )->name('asignaturas.index');

        Route::post(
            '/asignaturas',
            [AsignaturaController::class, 'store']
        )->name('asignaturas.store');

        Route::get(
            '/docentes',
            [DocenteController::class, 'index']
        )->name('docentes.index');
    });
