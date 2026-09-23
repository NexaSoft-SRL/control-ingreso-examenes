<?php

declare(strict_types=1);

use App\Modules\Administracion\Http\Controllers\AmbienteController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/ambientes')
    ->name('ambientes.')
    ->middleware('auth')
    ->group(function (): void {
        Route::get('/', [AmbienteController::class, 'index'])->name('index');
        Route::post('/', [AmbienteController::class, 'store'])->name('store');
        Route::get('/{ambiente}', [AmbienteController::class, 'show'])->name('show');
        Route::put('/{ambiente}', [AmbienteController::class, 'update'])->name('update');
        Route::delete('/{ambiente}', [AmbienteController::class, 'destroy'])->name('destroy');
    });
