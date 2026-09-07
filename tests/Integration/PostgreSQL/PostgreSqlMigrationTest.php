<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class PostgreSqlMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_current_migrations_can_be_built_from_scratch_on_postgresql(): void
    {
        $this->assertSame(
            'pgsql',
            DB::connection()->getDriverName()
        );

        $this->assertSame(
            'control_ingreso_testing',
            DB::connection()->getDatabaseName()
        );

        $statement = DB::connection()
            ->getPdo()
            ->query(
                <<<'SQL'
                SELECT tablename
                FROM pg_catalog.pg_tables
                WHERE schemaname = 'public'
                ORDER BY tablename
                SQL
            );

        if ($statement === false) {
            throw new RuntimeException(
                'No se pudo consultar el catalogo de tablas PostgreSQL.'
            );
        }

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        $tableNames = [];

        foreach ($rows as $tableName) {
            if (! is_string($tableName)) {
                throw new RuntimeException(
                    'PostgreSQL devolvio un nombre de tabla invalido.'
                );
            }

            $tableNames[] = $tableName;
        }

        foreach ([
            'cache',
            'cache_locks',
            'failed_jobs',
            'job_batches',
            'jobs',
            'migrations',
            'password_reset_tokens',
            'sessions',
            'users',
        ] as $expectedTable) {
            $this->assertContains(
                $expectedTable,
                $tableNames,
                "La tabla {$expectedTable} no fue creada."
            );
        }
    }
}
