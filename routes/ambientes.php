<?php

declare(strict_types=1);

use App\Modules\Administracion\Http\Controllers\AmbienteController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/ambientes')
    ->name('ambientes.')
    ->middleware(['auth'])
    ->group(function (): void {
        // HU-10: el docente lista ambientes para asignarlos a su examen,
        // aunque no tenga el permiso de asignaturas. Alcanza con uno de
        // los dos permisos.
        Route::get('/', [AmbienteController::class, 'index'])
            ->middleware('permiso:asignaturas_ambientes|examenes_normas')
            ->name('index');

        // El CRUD queda reservado al permiso de asignaturas.
        Route::middleware('permiso:asignaturas_ambientes')->group(function (): void {
            Route::post('/', [AmbienteController::class, 'store'])->name('store');
            Route::get('/{ambiente}', [AmbienteController::class, 'show'])->name('show');
            Route::put('/{ambiente}', [AmbienteController::class, 'update'])->name('update');
            Route::delete('/{ambiente}', [AmbienteController::class, 'destroy'])->name('destroy');
        });
    });
