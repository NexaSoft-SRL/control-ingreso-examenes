<?php

declare(strict_types=1);

return [
    /*
     * Disco seguro donde se guardan los archivos .sql de respaldo.
     */
    'disco' => env('RESPALDO_DISK', 'local'),

    /*
     * Directorio relativo dentro del disco seguro.
     */
    'directorio' => env('RESPALDO_DIR', 'respaldos'),

    /*
     * Version de esquema esperada (si es null, se obtiene de la tabla migrations).
     */
    'version_esquema' => env('RESPALDO_VERSION_ESQUEMA', null),

    /*
     * Tablas transitorias o de trabajo excluidas de los respaldos.
     */
    'tablas_excluidas' => [
        'respaldos',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'job_batches',
        'migrations',
        'presencias_puerta',
    ],
];
