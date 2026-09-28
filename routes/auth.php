<?php

use App\Modules\Administracion\Http\Controllers\AuthenticationController;
use App\Modules\Administracion\Http\Controllers\RoleController;
use App\Modules\Administracion\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/auth')
    ->name('auth.')
    ->group(function (): void {

        Route::post('/login', [AuthenticationController::class, 'login'])
            ->name('login');

        Route::post('/logout', [AuthenticationController::class, 'logout'])
            ->middleware('auth')
            ->name('logout');

        // La administracion de usuarios y roles exige sesion iniciada: la
        // lista de usuarios quedaba accesible para cualquiera.
        Route::prefix('admin')->middleware('auth')->group(function (): void {
            Route::get('/users', [RoleController::class, 'getUsers'])
                ->name('admin.users.index');
            Route::get('/roles', [RoleController::class, 'getRoles'])
                ->name('admin.roles.index');
            Route::post('/users', [UserController::class, 'store'])
                ->name('admin.users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])
                ->whereNumber('user')
                ->name('admin.users.update');
            Route::patch('/users/{user}/estado', [UserController::class, 'estado'])
                ->whereNumber('user')
                ->name('admin.users.estado');
            Route::get('/permissions', [RoleController::class, 'getPermissions'])
                ->name('admin.permissions.index');
            Route::put('/roles/{role}/permisos', [RoleController::class, 'updatePermissions'])
                ->whereNumber('role')
                ->name('admin.roles.permisos');
        });
    });
