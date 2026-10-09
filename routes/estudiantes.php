<?php

declare(strict_types=1);

use App\Modules\Estudiantes\Http\Controllers\CargaInscritosController;
use App\Modules\Estudiantes\Http\Controllers\ConflictoController;
use App\Modules\Estudiantes\Http\Controllers\InscritosController;
use App\Modules\Estudiantes\Http\Controllers\PadronController;
use Illuminate\Support\Facades\Route;

// Padron, cargas de inscritos y conflictos (Estudiantes): HU-13 y HU-14.
//
// Todas van bajo `/api`, con sesion (`auth`) y el permiso de su pantalla
// (`permiso:<clave>`; `permiso:a|b` cuando alcanza con uno de varios).

Route::prefix('api')->group(function (): void {
    // La administracion: el padron de toda la universidad.
    Route::middleware(['auth', 'permiso:padron_estudiantes'])->group(function (): void {
        Route::get('/estudiantes', [PadronController::class, 'index'])
            ->name('estudiantes.index');

        Route::get('/estudiantes/resumen', [PadronController::class, 'resumen'])
            ->name('estudiantes.resumen');

        Route::post('/estudiantes/cargas', [CargaInscritosController::class, 'facultad'])
            ->name('estudiantes.cargas.store');

        Route::get('/estudiantes/conflictos', [ConflictoController::class, 'index'])
            ->name('estudiantes.conflictos.index');

        Route::post('/estudiantes/conflictos/{conflicto}/resolucion', [ConflictoController::class, 'resolver'])
            ->whereNumber('conflicto')
            ->name('estudiantes.conflictos.resolver');

        Route::get('/estudiantes/{estudiante}', [PadronController::class, 'ficha'])
            ->whereNumber('estudiante')
            ->name('estudiantes.show');
    });

    // La plantilla la descargan los dos: alcanza con uno de los permisos.
    Route::get('/inscritos/plantilla', [InscritosController::class, 'plantilla'])
        ->middleware(['auth', 'permiso:padron_estudiantes|mis_grupos'])
        ->name('inscritos.plantilla');

    // El docente: la lista de cada uno de sus grupos.
    Route::middleware(['auth', 'permiso:mis_grupos'])->group(function (): void {
        Route::get('/docente/grupos/{grupo}/inscritos', [InscritosController::class, 'index'])
            ->whereNumber('grupo')
            ->name('docente.grupos.inscritos.index');

        Route::post('/docente/grupos/{grupo}/inscritos/carga', [CargaInscritosController::class, 'grupo'])
            ->whereNumber('grupo')
            ->name('docente.grupos.inscritos.carga');

        Route::get('/docente/grupos/{grupo}/inscritos/descarga', [InscritosController::class, 'descarga'])
            ->whereNumber('grupo')
            ->name('docente.grupos.inscritos.descarga');
    });
});
