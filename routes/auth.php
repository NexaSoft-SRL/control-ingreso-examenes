<?php

use App\Modules\Administracion\Http\Controllers\AuthenticationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/auth')
    ->name('auth.')
    ->group(function (): void {

        Route::post('/login', [AuthenticationController::class, 'login'])
            ->name('login');

        Route::post('/logout', [AuthenticationController::class, 'logout'])
            ->middleware('auth')
            ->name('logout');

        // Sin `auth`: un invitado recibe aqui su 401 con el mensaje propio.
        Route::get('/sesion', [AuthenticationController::class, 'sesion'])
            ->name('sesion');
    });
