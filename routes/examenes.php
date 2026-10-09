<?php

declare(strict_types=1);

use App\Modules\Examenes\Http\Controllers\ExamenController;
use App\Modules\Examenes\Http\Controllers\OpcionesExamenController;
use App\Modules\Examenes\Http\Controllers\PlantillaNormaController;
use Illuminate\Support\Facades\Route;

// Examenes y plantillas de normas (Examenes): HU-08.
//
// Todas van bajo `/api`, con sesion (`auth`) y el permiso de su pantalla
// (`permiso:<clave>`; `permiso:a|b` cuando alcanza con uno de varios). Que
// el examen o la plantilla sean de la cuenta lo comprueba cada accion.
Route::prefix('api')
    ->middleware(['auth', 'permiso:examenes'])
    ->group(function (): void {
        Route::get('/examenes', [ExamenController::class, 'index'])->name('examenes.index');
        Route::get('/examenes/tipos', [ExamenController::class, 'tipos'])->name('examenes.tipos');

        Route::get('/examenes/opciones/grupos', [OpcionesExamenController::class, 'grupos'])
            ->name('examenes.opciones.grupos');

        Route::get('/examenes/opciones/aulas', [OpcionesExamenController::class, 'aulas'])
            ->name('examenes.opciones.aulas');

        Route::post('/examenes', [ExamenController::class, 'store'])->name('examenes.store');

        Route::get('/examenes/{examen}', [ExamenController::class, 'show'])
            ->whereNumber('examen')
            ->name('examenes.show');

        Route::put('/examenes/{examen}', [ExamenController::class, 'update'])
            ->whereNumber('examen')
            ->name('examenes.update');

        Route::delete('/examenes/{examen}', [ExamenController::class, 'destroy'])
            ->whereNumber('examen')
            ->name('examenes.destroy');

        Route::get('/normas/plantillas', [PlantillaNormaController::class, 'index'])
            ->name('normas.plantillas.index');

        Route::post('/normas/plantillas', [PlantillaNormaController::class, 'store'])
            ->name('normas.plantillas.store');

        Route::put('/normas/plantillas/{plantilla}', [PlantillaNormaController::class, 'update'])
            ->whereNumber('plantilla')
            ->name('normas.plantillas.update');

        Route::delete('/normas/plantillas/{plantilla}', [PlantillaNormaController::class, 'destroy'])
            ->whereNumber('plantilla')
            ->name('normas.plantillas.destroy');
    });
