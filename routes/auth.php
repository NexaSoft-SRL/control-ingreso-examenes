<?php

use App\Modules\Administracion\Http\Controllers\AuthenticationController;
use App\Modules\Examenes\Http\Controllers\AccesoCodigoAulaController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/auth')
    ->name('auth.')
    ->group(function (): void {

        Route::post('/login', [AuthenticationController::class, 'login'])
            ->name('login');

        Route::post('/recuperar-contrasena', [AuthenticationController::class, 'solicitarRestablecimiento'])
            ->middleware('throttle:6,1')
            ->name('password.email');

        Route::post('/restablecer-contrasena', [AuthenticationController::class, 'restablecerContrasena'])
            ->middleware('throttle:6,1')
            ->name('password.update');

        Route::post('/cambiar-contrasena', [AuthenticationController::class, 'cambiarContrasena'])
            ->middleware('auth')
            ->name('password.change');

        Route::post('/logout', [AuthenticationController::class, 'logout'])
            ->middleware('auth')
            ->name('logout');

        // Sin `auth`: un invitado recibe aqui su 401 con el mensaje propio.
        Route::get('/sesion', [AuthenticationController::class, 'sesion'])
            ->name('sesion');

        Route::post('/codigo-aula', [AccesoCodigoAulaController::class, 'ingresar'])
            ->name('codigo-aula');

        Route::post('/codigo-aula/salir', [AccesoCodigoAulaController::class, 'salir'])
            ->name('codigo-aula.salir');

        Route::get('/codigo-aula/sesion', [AccesoCodigoAulaController::class, 'sesion'])
            ->name('codigo-aula.sesion');
    });
