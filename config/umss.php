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

    // La puerta de un examen abre estos minutos antes de su inicio.
    'minutos_apertura' => (int) env('UMSS_MINUTOS_APERTURA', 60),

    // El servidor no tiene tareas programadas: las alertas de reportes de los
    // examenes cerrados se procesan al terminar una peticion web, una vez por
    // minuto como mucho. Con `false` quedan solo para el programador.
    'alertas_al_responder' => (bool) env('UMSS_ALERTAS_AL_RESPONDER', true),

    // Un correo a una direccion que no existe sale bien y despues rebota al
    // buzon del remitente. Con `habilitado` el sistema lee esos avisos por
    // IMAP (cada 10 minutos con el programador, o al terminar una peticion
    // web como mucho cada 5) y marca esos envios como fallidos. Usa la misma
    // cuenta del correo saliente salvo que se indique otra.
    'rebotes' => [
        'habilitado' => (bool) env('UMSS_REVISAR_REBOTES', false),
        'host' => env('UMSS_REBOTES_HOST', 'imap.gmail.com'),
        'puerto' => (int) env('UMSS_REBOTES_PUERTO', 993),
        'usuario' => env('UMSS_REBOTES_USUARIO', env('MAIL_USERNAME')),
        'clave' => env('UMSS_REBOTES_CLAVE', env('MAIL_PASSWORD')),
    ],
];
