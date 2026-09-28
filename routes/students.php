<?php

use App\Modules\Administracion\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

// El padron es informacion de estudiantes: exige sesion iniciada.
Route::middleware('auth')->group(function (): void {
    Route::get('/students', [StudentController::class, 'index']);
    Route::post('/students/import', [StudentController::class, 'import']);
    Route::post('/students', [StudentController::class, 'store']);
    Route::put('/students/{student}', [StudentController::class, 'update']);
    Route::delete('/students/{student}', [StudentController::class, 'destroy']);
});
