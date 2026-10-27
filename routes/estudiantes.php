<?php

declare(strict_types=1);

use App\Modules\Estudiantes\Http\Controllers\CargaInscritosController;
use App\Modules\Estudiantes\Http\Controllers\ConflictoController;
use App\Modules\Estudiantes\Http\Controllers\FotoEstudianteController;
use App\Modules\Estudiantes\Http\Controllers\InscritosController;
use App\Modules\Estudiantes\Http\Controllers\PadronController;
use App\Modules\Estudiantes\Http\Controllers\PortalEstudianteController;
use App\Modules\Estudiantes\Http\Middleware\ExigirEstudiante;
use Illuminate\Support\Facades\Route;

// Padron, cargas de inscritos y conflictos (Estudiantes): HU-13 y HU-14;
// el portal del estudiante y las fotos.
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

        // Las fotos se sirven por permiso, nunca como archivo publico.
        Route::get('/estudiantes/{estudiante}/foto', [FotoEstudianteController::class, 'ver'])
            ->whereNumber('estudiante')
            ->name('estudiantes.foto');

        Route::post('/estudiantes/{estudiante}/foto', [FotoEstudianteController::class, 'reemplazar'])
            ->whereNumber('estudiante')
            ->name('estudiantes.foto.reemplazar');

        Route::get('/estudiantes/plazo-fotos', [FotoEstudianteController::class, 'plazo'])
            ->name('estudiantes.plazo-fotos');

        Route::put('/estudiantes/plazo-fotos', [FotoEstudianteController::class, 'fijarPlazo'])
            ->name('estudiantes.plazo-fotos.fijar');
    });

    // El estudiante, sin cuenta: un codigo al correo institucional. Los
    // limites de pedidos e intentos los aplica el controlador.
    Route::post('/auth/estudiante/codigo', [PortalEstudianteController::class, 'solicitar'])
        ->name('auth.estudiante.codigo');

    Route::post('/auth/estudiante', [PortalEstudianteController::class, 'entrar'])
        ->name('auth.estudiante');

    Route::delete('/auth/estudiante', [PortalEstudianteController::class, 'salir'])
        ->name('auth.estudiante.salir');

    Route::middleware(ExigirEstudiante::class)
        ->prefix('estudiante')
        ->name('estudiante.')
        ->group(function (): void {
            Route::get('/sesion', [PortalEstudianteController::class, 'sesion'])->name('sesion');
            Route::get('/examenes', [PortalEstudianteController::class, 'examenes'])->name('examenes');
            Route::get('/foto', [PortalEstudianteController::class, 'foto'])->name('foto');
            Route::post('/foto', [PortalEstudianteController::class, 'subirFoto'])->name('foto.subir');
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

        // El estudiante sube o cambia su foto: el docente se lo habilita.
        Route::post('/docente/grupos/{grupo}/inscritos/{estudiante}/foto', [InscritosController::class, 'habilitarFoto'])
            ->whereNumber(['grupo', 'estudiante'])
            ->name('docente.grupos.inscritos.foto');
    });
});
