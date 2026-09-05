<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';

$fail = static function (string $message): never {
    fwrite(STDERR, PHP_EOL.'POSTGRESQL TEST GUARD: ERROR'.PHP_EOL);
    fwrite(STDERR, $message.PHP_EOL.PHP_EOL);

    exit(1);
};

$environmentFile = $root.'/.env.testing';

if (! is_file($environmentFile)) {
    $fail(
        'No existe .env.testing. '
        .'Cree el archivo a partir de .env.testing.example.'
    );
}

$parsedEnvironment = parse_ini_file(
    $environmentFile,
    false,
    INI_SCANNER_RAW
);

if ($parsedEnvironment === false) {
    $fail('No se pudo leer .env.testing.');
}

/** @var array<string, string> $environment */
$environment = [];

foreach ($parsedEnvironment as $key => $value) {
    if (! is_string($key) || ! is_string($value)) {
        $fail(
            'Todas las variables de .env.testing deben tener '
            .'nombres y valores de tipo string.'
        );
    }

    $environment[$key] = $value;
}

$requiredValue = static function (string $key) use ($environment, $fail): string {
    $value = $environment[$key] ?? null;

    if ($value === null || $value === '') {
        $fail("Falta {$key} o su valor esta vacio en .env.testing.");
    }

    return $value;
};

$expected = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'control_ingreso_testing',
];

foreach ($expected as $key => $expectedValue) {
    $actualValue = $requiredValue($key);

    if ($actualValue !== $expectedValue) {
        $fail(
            sprintf(
                '%s debe ser "%s"; valor recibido: "%s".',
                $key,
                $expectedValue,
                $actualValue
            )
        );
    }
}

$dbHost = $requiredValue('DB_HOST');
$dbPort = $requiredValue('DB_PORT');
$dbDatabase = $requiredValue('DB_DATABASE');
$dbUsername = $requiredValue('DB_USERNAME');
$dbPassword = $requiredValue('DB_PASSWORD');

foreach ($environment as $key => $value) {
    if (! putenv("{$key}={$value}")) {
        $fail("No se pudo exportar la variable {$key}.");
    }

    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $dbHost,
    $dbPort,
    $dbDatabase
);

try {
    $pdo = new PDO(
        $dsn,
        $dbUsername,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $statement = $pdo->query(
        <<<'SQL'
        SELECT
            current_database() AS database,
            current_user AS username,
            current_setting('server_version') AS version
        SQL
    );

    if ($statement === false) {
        $fail('PostgreSQL no pudo consultar la identidad de la conexion.');
    }

    $identity = $statement->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    $fail(
        'No se pudo establecer la conexion PostgreSQL de testing: '
        .$exception->getMessage()
    );
}

if (! is_array($identity)) {
    $fail('PostgreSQL no devolvio la identidad de la conexion.');
}

$actualDatabase = $identity['database'] ?? null;
$actualUsername = $identity['username'] ?? null;
$actualVersion = $identity['version'] ?? null;

if (
    ! is_string($actualDatabase)
    || ! is_string($actualUsername)
    || ! is_string($actualVersion)
) {
    $fail('PostgreSQL devolvio una identidad de conexion invalida.');
}

if ($actualDatabase !== 'control_ingreso_testing') {
    $fail(
        'La conexion real no apunta a control_ingreso_testing. '
        .'Se bloquea la ejecucion.'
    );
}

if ($actualUsername !== $dbUsername) {
    $fail(
        sprintf(
            'El usuario PostgreSQL real debe ser "%s"; usuario recibido: "%s".',
            $dbUsername,
            $actualUsername
        )
    );
}

if ($actualVersion !== '15.10') {
    $fail(
        'La suite requiere PostgreSQL 15.10 exactamente; version encontrada: '
        .$actualVersion
    );
}

fwrite(
    STDOUT,
    sprintf(
        'POSTGRESQL TEST GUARD: OK [%s | PostgreSQL %s]%s',
        $actualDatabase,
        $actualVersion,
        PHP_EOL
    )
);
