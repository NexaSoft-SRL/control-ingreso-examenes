<?php

declare(strict_types=1);

use App\Modules\Administracion\Http\Controllers\BitacoraController;
use Illuminate\Support\Facades\Route;

// Bitacora de operaciones: una sola ruta, de lectura. Nadie la edita ni
// la borra.
Route::prefix('api')
    ->middleware(['auth', 'permiso:bitacora'])
    ->group(function (): void {
        Route::get('/bitacora', [BitacoraController::class, 'index'])
            ->name('bitacora.index');
    });
