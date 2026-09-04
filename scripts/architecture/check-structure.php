<?php

declare(strict_types=1);

use NexaSoft\Architecture\StructureChecker;

require_once __DIR__.'/src/StructureChecker.php';

try {
    $options = getopt('', [
        'root:',
        'contract:',
    ]);

    if ($options === false) {
        throw new RuntimeException(
            'No se pudieron interpretar los argumentos del comando.'
        );
    }

    $rootOption = $options['root'] ?? null;

    if (
        $rootOption !== null
        && (! is_string($rootOption) || $rootOption === '')
    ) {
        throw new RuntimeException(
            'La opción --root debe recibir una ruta no vacía.'
        );
    }

    $contractOption = $options['contract'] ?? null;

    if (
        $contractOption !== null
        && (! is_string($contractOption) || $contractOption === '')
    ) {
        throw new RuntimeException(
            'La opción --contract debe recibir una ruta no vacía.'
        );
    }

    $rootInput = $rootOption ?? dirname(__DIR__, 2);
    $root = realpath($rootInput);

    if ($root === false || ! is_dir($root)) {
        throw new RuntimeException(
            "No existe el directorio raíz indicado: {$rootInput}"
        );
    }

    $contractPath = $contractOption
        ?? $root.'/docs/architecture/architecture-contract.json';

    $checker = new StructureChecker(
        $root,
        $contractPath
    );

    $errors = $checker->check();
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "ARCHITECTURE CHECK: ERROR\n"
        ."=========================\n"
        .$exception->getMessage()
        .PHP_EOL
    );

    exit(1);
}

if ($errors !== []) {
    fwrite(STDERR, PHP_EOL);
    fwrite(STDERR, "ARCHITECTURE CHECK: FAILED\n");
    fwrite(STDERR, "==========================\n");

    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }

    fwrite(STDERR, PHP_EOL);
    fwrite(
        STDERR,
        count($errors)
        ." violacion(es) arquitectonica(s) encontrada(s).\n"
    );

    exit(1);
}

fwrite(STDOUT, PHP_EOL);
fwrite(STDOUT, "ARCHITECTURE CHECK: OK\n");
fwrite(STDOUT, "======================\n");
fwrite(
    STDOUT,
    "La estructura de app/ respeta el contrato arquitectonico.\n"
);

exit(0);
