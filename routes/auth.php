<?php

use App\Modules\Administracion\Http\Controllers\AuthenticationController;
use Illuminate\Support\Facades\Route;
use App\Modules\Administracion\Http\Controllers\RoleController;

Route::prefix('api/auth')
    ->name('auth.')
    ->group(function (): void {
        Route::post('/login', [AuthenticationController::class, 'login'])
            ->name('login');

        Route::post('/logout', [AuthenticationController::class, 'logout'])
            ->middleware('auth')
            ->name('logout');
            Route::prefix('admin')->group(function () {
    Route::get('/users', [RoleController::class, 'getUsers']);
    Route::get('/roles', [RoleController::class, 'getRoles']);
});
    });
