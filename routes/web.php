<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/usuarios.php';
require __DIR__.'/bitacora.php';
require __DIR__.'/academico.php';
require __DIR__.'/estudiantes.php';
require __DIR__.'/examenes.php';
require __DIR__.'/reportes.php';
require __DIR__.'/habilitacion.php';
require __DIR__.'/ingreso.php';
require __DIR__.'/monitoreo.php';
require __DIR__.'/respaldo.php';

/*
 * Vite deja el service worker y el manifiesto en public/build, pero un
 * service worker solo controla su propia carpeta: se sirven desde la raiz
 * para que alcance a toda la aplicacion. Van antes de la ruta de reserva.
 */
Route::get('/sw.js', function () {
    $ruta = public_path('build/sw.js');

    abort_unless(is_file($ruta), 404);

    return response()->file($ruta, [
        'Content-Type' => 'application/javascript',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache',
    ]);
})->name('pwa.sw');

Route::get('/manifest.webmanifest', function () {
    $ruta = public_path('build/manifest.webmanifest');

    abort_unless(is_file($ruta), 404);

    return response()->file($ruta, [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'no-cache',
    ]);
})->name('pwa.manifest');

/*
 * El cliente es una aplicacion de pagina unica: cualquier direccion que no sea
 * de la API devuelve la misma vista y React resuelve el enrutado.
 *
 * La ruta de reserva es lo que evita el error 404 al recargar una vista interna
 * en el servidor de TIS (paso 5 del procedimiento de despliegue).
 */
Route::view('/', 'app');

Route::view('/{cualquiera}', 'app')
    ->where('cualquiera', '^(?!api).*$');
