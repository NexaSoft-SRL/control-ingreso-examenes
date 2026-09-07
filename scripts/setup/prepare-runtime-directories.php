<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$directories = [
    'bootstrap/cache',
    'storage/app/private',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
    'storage/logs',
];

foreach ($directories as $relativeDirectory) {
    $directory = $root.'/'.$relativeDirectory;

    if (! is_dir($directory)
        && ! mkdir($directory, 0775, true)
        && ! is_dir($directory)
    ) {
        fwrite(
            STDERR,
            "No se pudo crear el directorio runtime: {$relativeDirectory}\n"
        );

        exit(1);
    }

    if (! is_writable($directory)) {
        fwrite(
            STDERR,
            "El directorio runtime no es escribible: {$relativeDirectory}\n"
        );

        exit(1);
    }
}

fwrite(STDOUT, "Runtime directories: OK\n");
