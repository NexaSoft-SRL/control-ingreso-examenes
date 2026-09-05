<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class PostgreSqlTransactionIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_rollback_does_not_persist_data(): void
    {
        $connection = $this->newPostgreSqlConnection();

        $email = 'rollback-probe@example.invalid';

        $this->assertSame(
            0,
            $this->countUsersByEmail($connection, $email)
        );

        $connection->beginTransaction();

        try {
            $this->insertUser(
                $connection,
                'Rollback Probe',
                $email
            );

            $this->assertSame(
                1,
                $this->countUsersByEmail($connection, $email)
            );

            $connection->rollBack();
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $this->assertSame(
            0,
            $this->countUsersByEmail($connection, $email)
        );
    }

    public function test_uncommitted_changes_are_not_visible_to_another_session(): void
    {
        $firstConnection = $this->newPostgreSqlConnection();
        $secondConnection = $this->newPostgreSqlConnection();

        $email = 'isolation-probe@example.invalid';

        $this->assertSame(
            0,
            $this->countUsersByEmail($firstConnection, $email)
        );

        $this->assertSame(
            0,
            $this->countUsersByEmail($secondConnection, $email)
        );

        $firstConnection->beginTransaction();

        try {
            $this->insertUser(
                $firstConnection,
                'Isolation Probe',
                $email
            );

            $this->assertSame(
                1,
                $this->countUsersByEmail($firstConnection, $email)
            );

            $this->assertSame(
                0,
                $this->countUsersByEmail($secondConnection, $email)
            );

            $firstConnection->rollBack();
        } finally {
            if ($firstConnection->inTransaction()) {
                $firstConnection->rollBack();
            }
        }

        $this->assertSame(
            0,
            $this->countUsersByEmail($secondConnection, $email)
        );
    }

    private function newPostgreSqlConnection(): PDO
    {
        $host = $this->requiredEnvironment('DB_HOST');
        $port = $this->requiredEnvironment('DB_PORT');
        $database = $this->requiredEnvironment('DB_DATABASE');
        $username = $this->requiredEnvironment('DB_USERNAME');
        $password = $this->requiredEnvironment('DB_PASSWORD');

        $pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname={$database}",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $statement = $pdo->query(
            'SELECT current_database()'
        );

        if ($statement === false) {
            throw new RuntimeException(
                'No se pudo consultar la base PostgreSQL actual.'
            );
        }

        $actualDatabase = $statement->fetchColumn();

        if (! is_string($actualDatabase)) {
            throw new RuntimeException(
                'PostgreSQL devolvio una identidad de base invalida.'
            );
        }

        $this->assertSame(
            'control_ingreso_testing',
            $actualDatabase
        );

        return $pdo;
    }

    private function insertUser(
        PDO $connection,
        string $name,
        string $email
    ): void {
        $statement = $connection->prepare(
            <<<'SQL'
            INSERT INTO users (
                name,
                email,
                password,
                created_at,
                updated_at
            )
            VALUES (
                :name,
                :email,
                :password,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            SQL
        );

        if ($statement === false) {
            throw new RuntimeException(
                'No se pudo preparar el INSERT de prueba.'
            );
        }

        if (! $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => 'not-used-in-test',
        ])) {
            throw new RuntimeException(
                'No se pudo ejecutar el INSERT de prueba.'
            );
        }
    }

    private function countUsersByEmail(
        PDO $connection,
        string $email
    ): int {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM users WHERE email = :email'
        );

        if ($statement === false) {
            throw new RuntimeException(
                'No se pudo preparar la consulta de usuarios.'
            );
        }

        if (! $statement->execute([
            'email' => $email,
        ])) {
            throw new RuntimeException(
                'No se pudo ejecutar la consulta de usuarios.'
            );
        }

        $count = $statement->fetchColumn();

        if (! is_int($count) && ! is_string($count)) {
            throw new RuntimeException(
                'PostgreSQL devolvio un contador invalido.'
            );
        }

        return (int) $count;
    }

    private function requiredEnvironment(string $key): string
    {
        $value = getenv($key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException(
                "La variable de entorno {$key} no esta definida."
            );
        }

        return $value;
    }
}
