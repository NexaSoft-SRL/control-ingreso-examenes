<?php

use App\Modules\Administracion\Http\Middleware\ExigirPermiso;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        /*
         * Toda la API responde JSON, tambien cuando el navegador abre una
         * descarga sin cabecera `Accept` (seccion 3.1 del contrato).
         */
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );

        // Sin sesion: el mismo cuerpo que da `permiso`.
        $exceptions->render(static function (AuthenticationException $error, Request $request): ?JsonResponse {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return new JsonResponse(['message' => 'No hay una sesión activa.'], 401);
        });

        // Los errores del marco que el cliente puede llegar a mostrar, en espanol.
        $exceptions->render(static function (HttpExceptionInterface $error, Request $request): ?JsonResponse {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $mensaje = match ($error->getStatusCode()) {
                404 => $error->getPrevious() instanceof ModelNotFoundException || $error->getMessage() === ''
                    || str_starts_with($error->getMessage(), 'The route ')
                        ? 'Recurso no encontrado.'
                        : null,
                405 => 'Método no permitido.',
                419 => 'La sesión venció. Vuelve a iniciar sesión.',
                429 => 'Demasiados intentos. Espera unos minutos.',
                default => null,
            };

            if ($mensaje === null) {
                return null;
            }

            return new JsonResponse(['message' => $mensaje], $error->getStatusCode(), $error->getHeaders());
        });
    })->create();
