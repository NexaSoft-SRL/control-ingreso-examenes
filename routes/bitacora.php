<?php

use App\Modules\Administracion\Http\Controllers\BitacoraController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/api/bitacora',
        [BitacoraController::class, 'index'],
    )->name('bitacora.index');
});
