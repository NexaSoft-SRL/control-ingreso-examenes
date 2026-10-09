<?php

declare(strict_types=1);

use App\Modules\Academico\Http\Controllers\DocenteController;
use App\Modules\Academico\Http\Controllers\FacultadController;
use App\Modules\Academico\Http\Controllers\GruposDocenteController;
use App\Modules\Academico\Http\Controllers\ImportacionOfertaController;
use App\Modules\Academico\Http\Controllers\OfertaController;
use App\Modules\Academico\Http\Controllers\PeriodoController;
use App\Modules\Academico\Http\Controllers\UbicacionController;
use Illuminate\Support\Facades\Route;

// Periodos, oferta, edificios, aulas y docentes (Academico): HU-10, HU-11 y HU-12.
//
// Todas van bajo `/api`, con sesion (`auth`) y el permiso de su pantalla
// (`permiso:<clave>`; `permiso:a|b` cuando alcanza con uno de varios).

// Catalogos que usan todas las pantallas: basta la sesion.
Route::prefix('api')->middleware('auth')->group(function (): void {
    Route::get('/facultades', [FacultadController::class, 'index'])
        ->name('facultades.index');
    Route::get('/periodos/vigentes', [PeriodoController::class, 'vigentes'])
        ->name('periodos.vigentes');
    Route::get('/edificios', [UbicacionController::class, 'edificios'])
        ->name('edificios.index');
    Route::get('/aulas', [UbicacionController::class, 'aulas'])
        ->name('aulas.index');
});

Route::prefix('api')->middleware(['auth', 'permiso:periodo_oferta'])->group(function (): void {
    Route::get('/periodos', [PeriodoController::class, 'index'])
        ->name('periodos.index');
    Route::get('/periodos/resumen', [PeriodoController::class, 'resumen'])
        ->name('periodos.resumen');
    Route::post('/periodos/detectar', [PeriodoController::class, 'detectar'])
        ->name('periodos.detectar');
    Route::put('/periodos/{periodo}', [PeriodoController::class, 'ajustar'])
        ->whereNumber('periodo')
        ->name('periodos.ajustar');

    Route::post('/oferta/importaciones', [ImportacionOfertaController::class, 'store'])
        ->name('oferta.importaciones.store');
});

// Las carreras las usan Periodo y el Padron, que tienen permisos distintos.
Route::get('/api/oferta/carreras', [OfertaController::class, 'carreras'])
    ->middleware(['auth', 'permiso:periodo_oferta|padron_estudiantes'])
    ->name('oferta.carreras');

Route::prefix('api')->middleware(['auth', 'permiso:aulas_docentes'])->group(function (): void {
    Route::get('/docentes', [DocenteController::class, 'index'])
        ->name('docentes.index');
    Route::get('/docentes/{docente}', [DocenteController::class, 'show'])
        ->whereNumber('docente')
        ->name('docentes.show');
    Route::post('/docentes/{docente}/cuenta', [DocenteController::class, 'activarCuenta'])
        ->whereNumber('docente')
        ->name('docentes.cuenta.activar');
});

Route::get('/api/docente/grupos', [GruposDocenteController::class, 'index'])
    ->middleware(['auth', 'permiso:mis_grupos'])
    ->name('docente.grupos');
