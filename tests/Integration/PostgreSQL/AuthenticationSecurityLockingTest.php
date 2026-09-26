<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class AuthenticationSecurityLockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_uses_postgresql_row_lock(): void
    {
        $lockingConnection = $this->newPostgreSqlConnection();

        $userId = $this->insertCommittedUser(
            $lockingConnection,
            'Locking Test',
            'locking-test@example.invalid',
            Hash::make('CorrectPassword123!')
        );

        try {
            $this->assertTrue(
                DB::table('usuarios')
                    ->where('id', $userId)
                    ->exists()
            );

            $lockingConnection->beginTransaction();

            $statement = $lockingConnection->prepare(
                'SELECT id FROM usuarios WHERE id = :id FOR UPDATE'
            );

            if ($statement === false) {
                throw new RuntimeException(
                    'No se pudo preparar el bloqueo PostgreSQL.'
                );
            }

            if (! $statement->execute([
                'id' => $userId,
            ])) {
                throw new RuntimeException(
                    'No se pudo ejecutar el bloqueo PostgreSQL.'
                );
            }

            $lockedUserId = $statement->fetchColumn();

            if (! is_int($lockedUserId) && ! is_string($lockedUserId)) {
                throw new RuntimeException(
                    'PostgreSQL no devolvió la fila bloqueada.'
                );
            }

            $this->assertSame(
                $userId,
                (int) $lockedUserId
            );

            DB::statement(
                "SET LOCAL lock_timeout TO '250ms'"
            );

            $gateway = app(
                AuthenticationSecurityGateway::class
            );

            try {
                $gateway->authenticate(
                    'locking-test@example.invalid',
                    'IncorrectPassword123!',
                    '192.0.2.20',
                    'HU-01 PostgreSQL locking test',
                );

                $this->fail(
                    'La autenticación debió esperar el bloqueo de fila.'
                );
            } catch (QueryException $exception) {
                $sqlState = $exception->errorInfo[0] ?? null;

                $this->assertSame(
                    '55P03',
                    $sqlState,
                    'PostgreSQL debe cancelar la espera por lock_timeout.'
                );
            }
        } finally {
            if ($lockingConnection->inTransaction()) {
                $lockingConnection->rollBack();
            }

            $this->deleteCommittedUser(
                $lockingConnection,
                $userId
            );
        }
    }

    public function test_failed_attempts_and_lockout_are_persisted_in_postgresql(): void
    {
        $user = UserFactory::new()->createOne([
            'email' => 'atomic-counter@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        config()->set(
            'auth_security.max_failed_attempts',
            3
        );

        $gateway = app(
            AuthenticationSecurityGateway::class
        );

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $gateway->authenticate(
                'atomic-counter@example.invalid',
                'IncorrectPassword123!',
                '192.0.2.21',
                'HU-01 PostgreSQL persistence test',
            );
        }

        $user->refresh();

        $this->assertSame(
            3,
            $user->failed_login_attempts
        );

        $this->assertNotNull(
            $user->locked_until
        );

        $this->assertSame(
            3,
            DB::table('login_attempts')
                ->where('user_id', $user->getKey())
                ->where('successful', false)
                ->count()
        );
    }

    private function newPostgreSqlConnection(): PDO
    {
        $host = $this->requiredEnvironment('DB_HOST');
        $port = $this->requiredEnvironment('DB_PORT');
        $database = $this->requiredEnvironment('DB_DATABASE');
        $username = $this->requiredEnvironment('DB_USERNAME');
        $password = $this->requiredEnvironment('DB_PASSWORD');

        $connection = new PDO(
            "pgsql:host={$host};port={$port};dbname={$database}",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $statement = $connection->query(
            'SELECT current_database()'
        );

        if ($statement === false) {
            throw new RuntimeException(
                'No se pudo consultar la base PostgreSQL.'
            );
        }

        $actualDatabase = $statement->fetchColumn();

        if ($actualDatabase !== 'control_ingreso_testing') {
            throw new RuntimeException(
                'La conexión auxiliar no apunta a control_ingreso_testing.'
            );
        }

        return $connection;
    }

    private function insertCommittedUser(
        PDO $connection,
        string $name,
        string $email,
        string $password,
    ): int {
        $statement = $connection->prepare(
            <<<'SQL'
            INSERT INTO usuarios (
                nombre,
                correo,
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
            RETURNING id
            SQL
        );

        if ($statement === false) {
            throw new RuntimeException(
                'No se pudo preparar el usuario de locking.'
            );
        }

        if (! $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ])) {
            throw new RuntimeException(
                'No se pudo crear el usuario de locking.'
            );
        }

        $userId = $statement->fetchColumn();

        if (! is_int($userId) && ! is_string($userId)) {
            throw new RuntimeException(
                'PostgreSQL devolvió un id de usuario inválido.'
            );
        }

        return (int) $userId;
    }

    private function deleteCommittedUser(
        PDO $connection,
        int $userId,
    ): void {
        $statement = $connection->prepare(
            'DELETE FROM usuarios WHERE id = :id'
        );

        if ($statement === false) {
            throw new RuntimeException(
                'No se pudo preparar la limpieza del usuario.'
            );
        }

        if (! $statement->execute([
            'id' => $userId,
        ])) {
            throw new RuntimeException(
                'No se pudo limpiar el usuario de locking.'
            );
        }
    }

    private function requiredEnvironment(string $key): string
    {
        $value = getenv($key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException(
                "La variable {$key} no está definida."
            );
        }

        return $value;
    }
}
