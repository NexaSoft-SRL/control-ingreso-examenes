<?php

use App\Modules\Administracion\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/admin/students')
    ->name('students.')
    ->middleware('auth')
    ->group(function (): void {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/{student}', [StudentController::class, 'show'])->name('show');
        Route::put('/{student}', [StudentController::class, 'update'])->name('update');
        Route::delete('/{student}', [StudentController::class, 'destroy'])->name('destroy');
    });
