<?php

use Illuminate\Support\Facades\Route;

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
