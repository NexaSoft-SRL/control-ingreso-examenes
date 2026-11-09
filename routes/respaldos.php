<?php

declare(strict_types=1);

use App\Modules\Administracion\Http\Controllers\RespaldoController;
use Illuminate\Support\Facades\Route;

// Respaldo y restauracion. Es la pantalla mas delicada del sistema: solo
// la alcanza quien tiene el permiso de administracion.
Route::prefix('api')
    ->middleware(['auth', 'permiso:respaldo_restauracion'])
    ->name('respaldos.')
    ->group(function (): void {
        Route::get('/respaldos', [RespaldoController::class, 'index'])->name('index');

        Route::post('/respaldos', [RespaldoController::class, 'store'])->name('store');

        Route::get('/respaldos/{respaldo}/descargar', [RespaldoController::class, 'descargar'])
            ->whereNumber('respaldo')
            ->name('descargar');

        Route::post('/respaldos/{respaldo}/restaurar', [RespaldoController::class, 'restaurar'])
            ->whereNumber('respaldo')
            ->name('restaurar');
    });
