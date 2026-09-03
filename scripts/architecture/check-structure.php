<?php

declare(strict_types=1);

use NexaSoft\Architecture\StructureChecker;

require_once __DIR__.'/src/StructureChecker.php';

$options = getopt('', [
    'root:',
    'contract:',
]);

$root = isset($options['root'])
    ? rtrim((string) $options['root'], DIRECTORY_SEPARATOR)
    : dirname(__DIR__, 2);

$contractPath = isset($options['contract'])
    ? (string) $options['contract']
    : $root.'/docs/architecture/architecture-contract.json';

try {
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
