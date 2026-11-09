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

        // Las 9 tablas del marco y las del dominio, mas la de control de
        // migraciones.
        $expectedTables = [
            'accesos_aula',
            'asignaturas',
            'aulas',
            'auxiliares',
            'bitacora_operaciones',
            'cache',
            'cache_locks',
            'cargas_inscritos',
            'carreras',
            'codigos_acceso_estudiante',
            'codigos_qr',
            'conflictos_padron',
            'docentes',
            'edificios',
            'envios_correo',
            'estudiantes',
            'evidencias_incidencia',
            'examen_aula',
            'examen_aula_apoyo',
            'examen_grupo',
            'examen_norma',
            'examenes',
            'facultades',
            'failed_jobs',
            'grupos',
            'habilitaciones',
            'horarios',
            'importaciones_oferta',
            'incidencias',
            'ingresos',
            'inscripciones',
            'job_batches',
            'jobs',
            'login_attempts',
            'migrations',
            'password_reset_tokens',
            'periodos',
            'permission_role',
            'permissions',
            'plan_estudios',
            'plantillas_norma',
            'plazos_foto',
            'presencias_puerta',
            'respaldos',
            'roles',
            'sessions',
            'users',
        ];

        // La lista es exacta: tampoco puede quedar ninguna tabla del
        // esquema anterior (students, ambientes, normas_examen...).
        $this->assertSame($expectedTables, $tableNames);
    }
}
