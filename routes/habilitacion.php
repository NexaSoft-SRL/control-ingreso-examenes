<?php

declare(strict_types=1);

use App\Modules\Habilitacion\Http\Controllers\ConsultaHabilitacionController;
use App\Modules\Habilitacion\Http\Controllers\HabilitacionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/habilitacion')
    ->middleware(['auth', 'permiso:habilitacion'])
    ->group(function (): void {
        Route::get('/examenes', [HabilitacionController::class, 'examenes']);
        Route::get('/examenes/{examen}/estudiantes', [HabilitacionController::class, 'estudiantes'])
            ->whereNumber('examen');
        Route::post('/examenes/{examen}/condiciones', [HabilitacionController::class, 'registrarCondiciones'])
            ->whereNumber('examen');
        Route::get('/examenes/{examen}/exportar', [HabilitacionController::class, 'exportar'])
            ->whereNumber('examen');
    });

// HU-13: quien atiende la puerta consulta, pero no arma la lista. Por eso
// exige el permiso del punto de control y no el de habilitación.
Route::prefix('api/consulta-habilitacion')
    ->middleware(['auth', 'permiso:punto_control'])
    ->group(function (): void {
        Route::get('/examenes', [ConsultaHabilitacionController::class, 'examenes']);
        Route::get('/examenes/{examen}', [ConsultaHabilitacionController::class, 'consultar'])
            ->whereNumber('examen');
    });
