<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CanonicalUserSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_functional_foreign_keys_reference_usuarios_and_never_users(): void
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT
                tc.table_name,
                kcu.column_name,
                ccu.table_name AS referenced_table
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.constraint_schema = kcu.constraint_schema
            JOIN information_schema.constraint_column_usage AS ccu
              ON tc.constraint_name = ccu.constraint_name
             AND tc.constraint_schema = ccu.constraint_schema
            WHERE tc.constraint_schema = 'public'
              AND tc.constraint_type = 'FOREIGN KEY'
              AND ccu.table_name IN ('users', 'usuarios')
            ORDER BY tc.table_name, kcu.column_name
            SQL
        );

        $actual = [];

        foreach ($rows as $rowObject) {
            /** @var array<string, mixed> $row */
            $row = (array) $rowObject;

            $tableName = $this->stringValue(
                $row['table_name'] ?? null
            );

            $columnName = $this->stringValue(
                $row['column_name'] ?? null
            );

            $referencedTable = $this->stringValue(
                $row['referenced_table'] ?? null
            );

            $this->assertSame(
                'usuarios',
                $referencedTable,
                sprintf(
                    '%s.%s no debe apuntar a users.',
                    $tableName,
                    $columnName
                )
            );

            $actual[] = sprintf(
                '%s.%s',
                $tableName,
                $columnName
            );
        }

        $this->assertSame(
            [
                'bitacora_operaciones.usuario_id',
                'docentes.user_id',
                'habilitaciones_examen.usuario_id',
                'login_attempts.user_id',
            ],
            $actual
        );
    }

    public function test_current_schema_contains_canonical_identity_and_repaired_columns(): void
    {
        $this->assertTrue(Schema::hasTable('usuarios'));

        $this->assertTrue(
            Schema::hasColumn('usuarios', 'role_id')
        );

        $this->assertTrue(
            Schema::hasColumn('asignaturas', 'carrera_id')
        );

        $columnObject = DB::table(
            'information_schema.columns'
        )
            ->where('table_schema', 'public')
            ->where('table_name', 'sessions')
            ->where('column_name', 'user_id')
            ->first();

        $this->assertNotNull($columnObject);

        /** @var array<string, mixed> $column */
        $column = (array) $columnObject;

        $this->assertSame(
            'character varying',
            $column['data_type']
        );

        $this->assertSame(
            255,
            $this->integerValue(
                $column['character_maximum_length'] ?? null
            )
        );
    }

    private function stringValue(mixed $value): string
    {
        if (! is_string($value)) {
            self::fail('Se esperaba un valor string.');
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

    public function test_usuarios_role_id_references_roles(): void
    {
        $rowObject = DB::selectOne(
            <<<'SQL'
            SELECT ccu.table_name AS referenced_table
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.constraint_column_usage AS ccu
              ON tc.constraint_name = ccu.constraint_name
             AND tc.constraint_schema = ccu.constraint_schema
            WHERE tc.constraint_schema = 'public'
              AND tc.table_name = 'usuarios'
              AND tc.constraint_name = 'usuarios_role_id_foreign'
              AND tc.constraint_type = 'FOREIGN KEY'
            SQL
        );

        $this->assertNotNull($rowObject);

        /** @var array<string, mixed> $row */
        $row = (array) $rowObject;

        $this->assertSame(
            'roles',
            $row['referenced_table']
        );
    }
}
