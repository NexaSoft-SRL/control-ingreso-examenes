<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

final class LegacySchemaUpgradeTest extends TestCase
{
    private ?string $originalDatabase = null;

    private ?string $temporaryDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(
            'pgsql',
            DB::connection()->getDriverName()
        );

        $this->originalDatabase = $this->stringConfig(
            'database.connections.pgsql.database'
        );

        $this->temporaryDatabase = sprintf(
            'control_ingreso_upgrade_test_%s',
            bin2hex(random_bytes(6))
        );

        $this->createTemporaryDatabase();

        Config::set(
            'database.connections.pgsql.database',
            $this->temporaryDatabase
        );

        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $this->createLegacySchema();

        $this->runFrozenHistoricalMigration(
            '0001_01_01_000001_create_cache_table.php'
        );

        $this->runFrozenHistoricalMigration(
            '0001_01_01_000002_create_jobs_table.php'
        );

        $this->runFrozenHistoricalMigration(
            '2026_09_17_004551_create_grupos_asignatura_table.php'
        );

        $this->markLegacyMigrationsAsExecuted();
    }

    protected function tearDown(): void
    {
        DB::disconnect('pgsql');

        if ($this->originalDatabase !== null) {
            Config::set(
                'database.connections.pgsql.database',
                $this->originalDatabase
            );

            DB::purge('pgsql');
            DB::reconnect('pgsql');
        }

        if ($this->temporaryDatabase !== null) {
            $pdo = $this->adminConnection();

            $pdo->exec(
                sprintf(
                    'DROP DATABASE IF EXISTS "%s" WITH (FORCE)',
                    $this->temporaryDatabase
                )
            );
        }

        parent::tearDown();
    }

    public function test_legacy_schema_with_data_upgrades_without_data_loss(): void
    {
        $legacyPassword = 'LegacySecret!123';
        $legacyHash = Hash::make($legacyPassword);

        DB::table('users')->insert([
            'id' => 77,
            'name' => 'Legacy Upgrade Probe',
            'email' => 'legacy-upgrade@example.invalid',
            'password' => $legacyHash,
            'is_active' => true,
            'failed_login_attempts' => 2,
            'locked_until' => '2026-09-27 15:00:00',
            'last_login_at' => '2026-09-26 10:30:00',
            'created_at' => '2026-09-20 08:00:00',
            'updated_at' => '2026-09-26 11:00:00',
        ]);

        DB::table('docentes')->insert([
            'id' => 91,
            'user_id' => 77,
            'codigo_docente' => 'DOC-LEGACY-77',
            'nombres' => 'Legacy',
            'apellidos' => 'Docente',
            'correo' => 'legacy-docente@example.invalid',
            'estado' => true,
        ]);

        DB::table('login_attempts')->insert([
            'id' => 92,
            'user_id' => 77,
            'identifier' => 'legacy-upgrade@example.invalid',
            'successful' => false,
            'ip_address' => '192.0.2.77',
            'user_agent' => 'Legacy Upgrade Test',
            'attempted_at' => '2026-09-27 15:05:00',
        ]);

        DB::table('bitacora_operaciones')->insert([
            'id' => 93,
            'usuario_id' => 77,
            'operacion' => 'LEGACY_TEST',
            'tabla_afectada' => 'users',
            'registro_id' => 77,
            'descripcion' => 'Prueba de preservacion durante upgrade',
            'fecha_operacion' => '2026-09-27 15:10:00',
        ]);

        $exitCode = Artisan::call('migrate', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->assertSame(
            0,
            $exitCode,
            Artisan::output()
        );

        foreach ([
            'ambientes',
            'asignaturas',
            'bitacora_operaciones',
            'cache',
            'cache_locks',
            'docentes',
            'failed_jobs',
            'grupos_asignatura',
            'job_batches',
            'jobs',
            'login_attempts',
            'migrations',
            'password_reset_tokens',
            'permission_role',
            'permissions',
            'roles',
            'sessions',
            'students',
            'users',
            'usuarios',
        ] as $expectedTable) {
            $this->assertTrue(
                Schema::hasTable($expectedTable),
                "Falta la tabla {$expectedTable} después del upgrade legacy."
            );
        }

        $this->assertTrue(
            Schema::hasColumn('asignaturas', 'carrera_id')
        );

        $userObject = DB::table('usuarios')
            ->where('id', 77)
            ->first();

        $this->assertNotNull($userObject);

        /** @var array<string, mixed> $user */
        $user = (array) $userObject;

        $this->assertSame(
            'Legacy Upgrade Probe',
            $user['name']
        );

        $this->assertSame(
            'Legacy Upgrade Probe',
            $user['nombre']
        );

        $this->assertSame(
            'legacy-upgrade@example.invalid',
            $user['email']
        );

        $this->assertSame(
            'legacy-upgrade@example.invalid',
            $user['correo']
        );

        $this->assertSame(
            $legacyHash,
            $user['password']
        );

        $this->assertTrue(
            Hash::check(
                $legacyPassword,
                (string) $user['password']
            )
        );

        $this->assertTrue((bool) $user['is_active']);

        $this->assertSame(
            2,
            $this->integerValue(
                $user['failed_login_attempts'] ?? null
            )
        );

        $this->assertSame(
            '2026-09-27 15:00:00',
            $user['locked_until']
        );

        $this->assertSame(
            '2026-09-26 10:30:00',
            $user['last_login_at']
        );

        $this->assertSame(
            77,
            $this->integerValue(
                DB::table('docentes')
                    ->where('id', 91)
                    ->value('user_id')
            )
        );

        $this->assertSame(
            77,
            $this->integerValue(
                DB::table('login_attempts')
                    ->where('id', 92)
                    ->value('user_id')
            )
        );

        $this->assertSame(
            77,
            $this->integerValue(
                DB::table('bitacora_operaciones')
                    ->where('id', 93)
                    ->value('usuario_id')
            )
        );

        $this->assertForeignKeyReferences(
            'docentes',
            'docentes_user_id_foreign',
            'usuarios'
        );

        $this->assertForeignKeyReferences(
            'login_attempts',
            'login_attempts_user_id_foreign',
            'usuarios'
        );

        $this->assertForeignKeyReferences(
            'bitacora_operaciones',
            'bitacora_operaciones_usuario_id_foreign',
            'usuarios'
        );

        $sessionColumnObject = DB::table(
            'information_schema.columns'
        )
            ->where('table_schema', 'public')
            ->where('table_name', 'sessions')
            ->where('column_name', 'user_id')
            ->first();

        $this->assertNotNull($sessionColumnObject);

        /** @var array<string, mixed> $sessionColumn */
        $sessionColumn = (array) $sessionColumnObject;

        $this->assertSame(
            'character varying',
            $sessionColumn['data_type']
        );

        $this->assertSame(
            255,
            $this->integerValue(
                $sessionColumn['character_maximum_length'] ?? null
            )
        );

        $newUserId = DB::table('usuarios')->insertGetId([
            'name' => 'Sequence Probe',
            'email' => 'sequence-probe@example.invalid',
            'nombre' => 'Sequence Probe',
            'correo' => 'sequence-probe@example.invalid',
            'password' => Hash::make('SequenceSecret!123'),
        ]);

        $this->assertGreaterThan(
            77,
            $newUserId,
            'La secuencia de usuarios no se actualizo tras migrar IDs legacy.'
        );

        $secondExitCode = Artisan::call('migrate', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->assertSame(0, $secondExitCode);

        $this->assertStringContainsString(
            'Nothing to migrate',
            Artisan::output()
        );
    }

    private function stringConfig(string $key): string
    {
        $value = config($key);

        if (! is_string($value)) {
            self::fail(
                sprintf(
                    'La configuracion %s debe ser string.',
                    $key
                )
            );
        }

        return $value;
    }

    private function integerValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        self::fail('Se esperaba un valor entero.');
    }

    private function createTemporaryDatabase(): void
    {
        $this->assertNotNull($this->temporaryDatabase);

        $pdo = $this->adminConnection();

        $pdo->exec(
            sprintf(
                'CREATE DATABASE "%s"',
                $this->temporaryDatabase
            )
        );
    }

    private function adminConnection(): PDO
    {
        $host = $this->stringConfig(
            'database.connections.pgsql.host'
        );

        $port = $this->stringConfig(
            'database.connections.pgsql.port'
        );

        $username = $this->stringConfig(
            'database.connections.pgsql.username'
        );

        $password = $this->stringConfig(
            'database.connections.pgsql.password'
        );

        return new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=postgres',
                $host,
                $port
            ),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    private function createLegacySchema(): void
    {
        $statements = [
            <<<'SQL'
            CREATE TABLE migrations (
                id serial PRIMARY KEY,
                migration varchar(255) NOT NULL,
                batch integer NOT NULL
            )
            SQL,
            <<<'SQL'
            CREATE TABLE users (
                id bigserial PRIMARY KEY,
                name varchar(255) NOT NULL,
                email varchar(255) NOT NULL UNIQUE,
                email_verified_at timestamp(0) without time zone NULL,
                password varchar(255) NOT NULL,
                remember_token varchar(100) NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                is_active boolean NOT NULL DEFAULT true,
                failed_login_attempts integer NOT NULL DEFAULT 0,
                locked_until timestamp(0) without time zone NULL,
                last_login_at timestamp(0) without time zone NULL,
                CONSTRAINT users_failed_login_attempts_non_negative
                    CHECK (failed_login_attempts >= 0)
            )
            SQL,
            <<<'SQL'
            CREATE TABLE password_reset_tokens (
                email varchar(255) PRIMARY KEY,
                token varchar(255) NOT NULL,
                created_at timestamp(0) without time zone NULL
            )
            SQL,
            <<<'SQL'
            CREATE TABLE sessions (
                id varchar(255) PRIMARY KEY,
                user_id bigint NULL,
                ip_address varchar(45) NULL,
                user_agent text NULL,
                payload text NOT NULL,
                last_activity integer NOT NULL
            )
            SQL,
            <<<'SQL'
            CREATE INDEX sessions_user_id_index
            ON sessions (user_id)
            SQL,
            <<<'SQL'
            CREATE INDEX sessions_last_activity_index
            ON sessions (last_activity)
            SQL,
            <<<'SQL'
            CREATE TABLE asignaturas (
                id bigserial PRIMARY KEY,
                codigo varchar(30) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                semestre varchar(20) NULL,
                descripcion text NULL,
                estado boolean NOT NULL DEFAULT true,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL
            )
            SQL,
            <<<'SQL'
            CREATE TABLE docentes (
                id bigserial PRIMARY KEY,
                user_id bigint NULL UNIQUE,
                codigo_docente varchar(50) NOT NULL UNIQUE,
                nombres varchar(100) NOT NULL,
                apellidos varchar(100) NOT NULL,
                correo varchar(150) NULL UNIQUE,
                telefono varchar(30) NULL,
                estado boolean NOT NULL DEFAULT true,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT docentes_user_id_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL
            )
            SQL,
            <<<'SQL'
            CREATE TABLE login_attempts (
                id bigserial PRIMARY KEY,
                user_id bigint NULL,
                identifier varchar(255) NOT NULL,
                successful boolean NOT NULL DEFAULT false,
                ip_address varchar(45) NULL,
                user_agent text NULL,
                attempted_at timestamp(0) without time zone
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT login_attempts_user_id_foreign
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
            )
            SQL,
            <<<'SQL'
            CREATE INDEX login_attempts_identifier_attempted_at_idx
            ON login_attempts (identifier, attempted_at)
            SQL,
            <<<'SQL'
            CREATE INDEX login_attempts_user_attempted_at_idx
            ON login_attempts (user_id, attempted_at)
            SQL,
            <<<'SQL'
            CREATE TABLE bitacora_operaciones (
                id bigserial PRIMARY KEY,
                usuario_id bigint NULL,
                operacion varchar(100) NOT NULL,
                tabla_afectada varchar(100) NULL,
                registro_id bigint NULL,
                descripcion text NULL,
                fecha_operacion timestamp(0) without time zone
                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT bitacora_operaciones_usuario_id_foreign
                    FOREIGN KEY (usuario_id)
                    REFERENCES users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL
            )
            SQL,
        ];

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }

    private function runFrozenHistoricalMigration(
        string $filename
    ): void {
        $path = database_path(
            'migrations/'.$filename
        );

        if (! is_file($path)) {
            self::fail(
                "No existe la migración histórica {$filename}."
            );
        }

        $migration = require $path;

        if (! $migration instanceof Migration) {
            self::fail(
                "{$filename} no devolvió una migración válida."
            );
        }

        $up = new \ReflectionMethod($migration, 'up');
        $up->invoke($migration);
    }

    private function markLegacyMigrationsAsExecuted(): void
    {
        $migrations = [
            '0001_01_01_000000_create_users_table',
            '0001_01_01_000001_create_cache_table',
            '0001_01_01_000002_create_jobs_table',
            '2026_09_16_140731_add_authentication_security_fields_and_login_attempts',
            '2026_09_17_004551_create_asignaturas_table',
            '2026_09_17_004551_create_docentes_table',
            '2026_09_17_004551_create_grupos_asignatura_table',
            '2026_09_17_203234_create_bitacora_operaciones_table',
        ];

        foreach ($migrations as $migration) {
            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => 1,
            ]);
        }
    }

    private function assertForeignKeyReferences(
        string $table,
        string $constraint,
        string $expectedTable
    ): void {
        $result = DB::selectOne(
            <<<'SQL'
            SELECT ccu.table_name AS referenced_table
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
             AND ccu.constraint_schema = tc.constraint_schema
            WHERE tc.constraint_schema = 'public'
              AND tc.table_name = ?
              AND tc.constraint_name = ?
              AND tc.constraint_type = 'FOREIGN KEY'
            SQL,
            [$table, $constraint]
        );

        $this->assertNotNull(
            $result,
            "No existe la FK {$constraint}."
        );

        /** @var array<string, mixed> $row */
        $row = (array) $result;

        $this->assertSame(
            $expectedTable,
            $row['referenced_table']
        );
    }
}
