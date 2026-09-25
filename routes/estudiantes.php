<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Estudiantes\Http\Controllers\EstudianteController;

Route::prefix('estudiantes')->group(function () {
    Route::post('/carga-masiva', [EstudianteController::class, 'cargaMasiva']);
});