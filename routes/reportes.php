<?php

declare(strict_types=1);

use App\Modules\Reportes\Http\Controllers\ReporteDocenteController;
use App\Modules\Reportes\Http\Controllers\ReporteUniversidadController;
use Illuminate\Support\Facades\Route;

// Reportes (rutas 73 a 80, mas el resumen por estudiante y las
// incidencias de un examen). Las descargas y consultas son de lectura;
// las alertas del docente añaden cambios acotados a sus preferencias/lectura.

// Del administrador: toda la universidad.
Route::middleware(['auth', 'permiso:reportes_universidad'])->group(function (): void {
    Route::get(
        '/api/reportes/examenes',
        [ReporteUniversidadController::class, 'examenes'],
    )->name('reportes.examenes');

    // La bitacora de cualquier examen, para auditar un reclamo.
    Route::get(
        '/api/reportes/universidad/examenes/{examen}/bitacora',
        [ReporteUniversidadController::class, 'bitacora'],
    )->whereNumber('examen')->name('reportes.universidad.bitacora');

    Route::get(
        '/api/reportes/universidad/bitacora/exportar',
        [ReporteUniversidadController::class, 'exportarBitacora'],
    )->name('reportes.universidad.bitacora.exportar');

    foreach (['horarios', 'registrados', 'intentos', 'estudiantes'] as $reporte) {
        Route::get(
            "/api/reportes/{$reporte}/exportar",
            [ReporteUniversidadController::class, 'exportar'],
        )->defaults('reporte', $reporte)
            ->name("reportes.{$reporte}.exportar");
    }
});

// Del docente: sus examenes.
Route::middleware(['auth', 'permiso:reportes_examenes'])->group(function (): void {
    Route::get(
        '/api/reportes/mis-examenes',
        [ReporteDocenteController::class, 'misExamenes'],
    )->name('reportes.mis-examenes');

    Route::get(
        '/api/reportes/comparacion/opciones',
        [ReporteDocenteController::class, 'opcionesComparacion'],
    )->name('reportes.comparacion.opciones');

    Route::get(
        '/api/reportes/comparacion',
        [ReporteDocenteController::class, 'comparar'],
    )->name('reportes.comparacion');

    Route::get(
        '/api/reportes/consolidado/exportar',
        [ReporteDocenteController::class, 'exportarConsolidado'],
    )->name('reportes.consolidado.exportar');

    Route::get(
        '/api/reportes/alertas',
        [ReporteDocenteController::class, 'alertas'],
    )->name('reportes.alertas');

    Route::put(
        '/api/reportes/alertas/preferencias',
        [ReporteDocenteController::class, 'guardarPreferenciasAlertas'],
    )->name('reportes.alertas.preferencias');

    Route::post(
        '/api/reportes/alertas/{alerta}/leida',
        [ReporteDocenteController::class, 'marcarAlertaLeida'],
    )->whereNumber('alerta')->name('reportes.alertas.leida');

    Route::get(
        '/api/reportes/examenes/{examen}/bitacora',
        [ReporteDocenteController::class, 'bitacora'],
    )->whereNumber('examen')->name('reportes.bitacora');

    foreach (['asistencia', 'ausentes', 'incidencias', 'bitacora'] as $reporte) {
        Route::get(
            "/api/reportes/{$reporte}/exportar",
            [ReporteDocenteController::class, 'exportar'],
        )->defaults('reporte', $reporte)
            ->name("reportes.{$reporte}.exportar");
    }
});

// Del docente: la lista de un grupo suyo.
Route::middleware(['auth', 'permiso:mis_grupos'])->group(function (): void {
    Route::get(
        '/api/reportes/grupos/{grupo}/estudiantes',
        [ReporteDocenteController::class, 'estudiantesDeGrupo'],
    )->whereNumber('grupo')
        ->name('reportes.grupo.estudiantes');
});
