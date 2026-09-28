<?php

use App\Modules\Administracion\Http\Middleware\ExigirPermiso;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // HU-02: las rutas declaran con "permiso:<nombre>" que atribucion
        // exigen al rol de quien las llama.
        $middleware->alias([
            'permiso' => ExigirPermiso::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
