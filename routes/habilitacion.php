<?php

declare(strict_types=1);

use App\Modules\Habilitacion\Http\Controllers\HabilitacionController;
use App\Modules\Habilitacion\Http\Controllers\RepartoController;
use Illuminate\Support\Facades\Route;

// Habilitacion y reparto por aula (Habilitacion): HU-09.
//
// Todas van bajo `/api`, con sesion (`auth`) y el permiso de su pantalla
// (`permiso:<clave>`). Ademas del permiso, las acciones exigen ser docente
// del examen.
Route::middleware(['auth', 'permiso:habilitacion'])->group(function (): void {
    Route::get(
        '/api/examenes/{examen}/habilitaciones',
        [HabilitacionController::class, 'index'],
    )->whereNumber('examen')->name('habilitacion.index');

    Route::post(
        '/api/examenes/{examen}/habilitaciones',
        [HabilitacionController::class, 'store'],
    )->whereNumber('examen')->name('habilitacion.cambiar');

    Route::post(
        '/api/examenes/{examen}/reparto',
        [RepartoController::class, 'store'],
    )->whereNumber('examen')->name('habilitacion.repartir');
});
