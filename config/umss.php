<?php

declare(strict_types=1);

return [
    /*
     * Carpeta de la que el importador lee la oferta de GENDA (un JSON por
     * facultad, `locations.json` y `pensum.json`). Las pruebas la apuntan a
     * `tests/Fixtures/genda`.
     */
    'ruta_genda' => env('UMSS_RUTA_GENDA', database_path('umss/genda')),

    // Registro de fuentes publicas: de aqui salen las fechas de los periodos.
    'ruta_fuentes' => env('UMSS_RUTA_FUENTES', database_path('umss/fuentes.json')),

    // Las facultades cuya oferta se importa: clave = nombre de su archivo.
    'facultades' => ['fcyt', 'fce', 'fhce', 'fach'],

    /*
     * Zona horaria unica del sistema: es la de la aplicacion
     * (`config/app.php`, variable `APP_TIMEZONE`) y la de la sesion de
     * PostgreSQL (`config/database.php`). `now()`, la fecha y la hora de un
     * examen y todos los instantes guardados estan en esta zona.
     */
    'zona_horaria' => env('APP_TIMEZONE', 'America/La_Paz'),

    // El correo de un estudiante es su codigo universitario en este dominio.
    'dominio_correo_estudiantes' => 'est.umss.edu',
];
