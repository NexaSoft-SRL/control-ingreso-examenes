<?php

declare(strict_types=1);

use App\Modules\Examenes\Http\Controllers\CalendarioDeAulasController;
use Illuminate\Support\Facades\Route;

// La vista de calendario de las aulas (Examenes): solo lectura, con el
// permiso de quien registra examenes.
Route::prefix('api')
    ->middleware(['auth', 'permiso:examenes'])
    ->group(function (): void {
        Route::get('/calendario/aulas', [CalendarioDeAulasController::class, 'index'])
            ->name('calendario.aulas');
    });
