<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class PostgreSqlEnvironmentTest extends TestCase
{
    public function test_it_runs_against_the_expected_postgresql_environment(): void
    {
        $this->assertSame(
            'testing',
            app()->environment()
        );

        $this->assertSame(
            'pgsql',
            config('database.default')
        );

        $this->assertSame(
            'control_ingreso_testing',
            DB::connection()->getDatabaseName()
        );

        $this->assertSame(
            'control_ingreso_testing',
            $this->queryScalar('SELECT current_database()')
        );

        $this->assertSame(
            '15.10',
            $this->queryScalar(
                "SELECT current_setting('server_version')"
            )
        );
    }

    private function queryScalar(string $sql): string
    {
        $statement = DB::connection()
            ->getPdo()
            ->query($sql);

        if ($statement === false) {
            throw new RuntimeException(
                'No se pudo ejecutar la consulta PostgreSQL.'
            );
        }

        $value = $statement->fetchColumn();

        if (! is_string($value)) {
            throw new RuntimeException(
                'PostgreSQL no devolvio un valor string.'
            );
        }

        return $value;
    }
}
