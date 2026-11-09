<?php

use App\Modules\Administracion\Http\Controllers\AuthenticationController;
use App\Modules\Administracion\Http\Controllers\ContrasenaController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/auth')
    ->name('auth.')
    ->group(function (): void {
        Route::post('/login', [AuthenticationController::class, 'login'])
            ->name('login');

        Route::post('/logout', [AuthenticationController::class, 'logout'])
            ->middleware('auth')
            ->name('logout');

        // Sin `auth`: responde ella misma el 401 con el mensaje del sistema.
        Route::get('/sesion', [AuthenticationController::class, 'sesion'])
            ->name('sesion');

        Route::post('/contrasena', [ContrasenaController::class, 'cambiar'])
            ->middleware('auth')
            ->name('contrasena.cambiar');

        Route::post(
            '/contrasena/olvide',
            [ContrasenaController::class, 'solicitarRestablecimiento']
        )->name('contrasena.olvide');

        Route::post(
            '/contrasena/restablecer',
            [ContrasenaController::class, 'restablecer']
        )->name('contrasena.restablecer');
    });
