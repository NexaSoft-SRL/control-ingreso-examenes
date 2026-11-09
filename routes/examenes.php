<?php

declare(strict_types=1);

use App\Modules\Examenes\Http\Controllers\AuxiliarController;
use App\Modules\Examenes\Http\Controllers\CodigoAulaController;
use App\Modules\Examenes\Http\Controllers\ExamenController;
use App\Modules\Examenes\Http\Controllers\OpcionesExamenController;
use App\Modules\Examenes\Http\Controllers\PlantillaNormaController;
use Illuminate\Support\Facades\Route;

// Examenes, sus opciones, plantillas de normas, auxiliares y codigos de aula
// (Examenes). El permiso es el de la pantalla; que el examen o la plantilla
// sean de la cuenta lo comprueba cada accion.
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

        Route::post('/examenes/{examen}/finalizar', [ExamenController::class, 'finalizar'])
            ->whereNumber('examen')
            ->name('examenes.finalizar');

        Route::get('/examenes/{examen}/aulas/{aula}/codigo', [CodigoAulaController::class, 'show'])
            ->whereNumber(['examen', 'aula'])
            ->name('examenes.aulas.codigo.show');

        Route::post('/examenes/{examen}/aulas/{aula}/codigo', [CodigoAulaController::class, 'regenerar'])
            ->whereNumber(['examen', 'aula'])
            ->name('examenes.aulas.codigo.regenerar');

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

        Route::get('/auxiliares', [AuxiliarController::class, 'index'])->name('auxiliares.index');
        Route::post('/auxiliares', [AuxiliarController::class, 'store'])->name('auxiliares.store');

        Route::delete('/auxiliares/{auxiliar}', [AuxiliarController::class, 'destroy'])
            ->whereNumber('auxiliar')
            ->name('auxiliares.destroy');
    });
