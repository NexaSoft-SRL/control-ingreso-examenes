<?php

declare(strict_types=1);

use App\Modules\Ingreso\Http\Controllers\PuntoControlController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:punto_control'])
    ->prefix('api/examenes/{examen}/aulas/{aula}/punto-control')
    ->whereNumber('examen')
    ->whereNumber('aula')
    ->group(function (): void {
        Route::get('candidato', [
            PuntoControlController::class,
            'candidato',
        ])->name('ingreso.candidato');

        Route::post('ingresos', [
            PuntoControlController::class,
            'registrar',
        ])->name('ingreso.registrar');

        Route::get('ingresos', [
            PuntoControlController::class,
            'recientes',
        ])->name('ingreso.recientes');
    });
