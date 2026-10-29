<?php

declare(strict_types=1);

use App\Modules\Administracion\Http\Controllers\RespaldoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:respaldo_restauracion'])->group(function (): void {
    Route::get('/api/respaldos', [RespaldoController::class, 'index'])->name('respaldos.index');
    Route::post('/api/respaldos', [RespaldoController::class, 'store'])->name('respaldos.store');
});
