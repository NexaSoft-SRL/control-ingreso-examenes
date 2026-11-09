<?php

declare(strict_types=1);

use App\Modules\Administracion\Http\Controllers\MatrizPermisosController;
use App\Modules\Administracion\Http\Controllers\RolController;
use App\Modules\Administracion\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Cuentas, roles y matriz de permisos (Administracion). No hay registro
// publico: las cuentas se crean aqui con un rol existente.
Route::prefix('api')
    ->middleware(['auth', 'permiso:usuarios_roles'])
    ->group(function (): void {
        Route::get('/usuarios', [UsuarioController::class, 'index'])
            ->name('usuarios.index');

        Route::post('/usuarios', [UsuarioController::class, 'store'])
            ->name('usuarios.store');

        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])
            ->whereNumber('usuario')
            ->name('usuarios.update');

        Route::post(
            '/usuarios/{usuario}/contrasena-temporal',
            [UsuarioController::class, 'contrasenaTemporal'],
        )
            ->whereNumber('usuario')
            ->name('usuarios.contrasena_temporal');

        Route::get('/roles', [RolController::class, 'index'])
            ->name('roles.index');

        Route::post('/roles', [RolController::class, 'store'])
            ->name('roles.store');

        // Los tres roles de inicio vuelven a su reparto de fabrica.
        Route::post('/roles/restablecer', [RolController::class, 'restablecer'])
            ->name('roles.restablecer');

        Route::put('/roles/{rol}', [RolController::class, 'update'])
            ->whereNumber('rol')
            ->name('roles.update');

        Route::delete('/roles/{rol}', [RolController::class, 'destroy'])
            ->whereNumber('rol')
            ->name('roles.destroy');

        Route::get('/permisos/matriz', MatrizPermisosController::class)
            ->name('permisos.matriz');
    });
