<?php

declare(strict_types=1);

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
