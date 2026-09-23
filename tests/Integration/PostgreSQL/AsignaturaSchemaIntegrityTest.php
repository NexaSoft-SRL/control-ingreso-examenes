<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AsignaturaSchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_rejects_nonexistent_teacher(): void
    {
        $asignaturaId = $this->createSubject(
            'INF-101',
            'Programación I',
        );

        $this->expectException(QueryException::class);

        DB::table('grupos_asignatura')->insert([
            'asignatura_id' => $asignaturaId,
            'docente_id' => 999999,
            'codigo_grupo' => '1',
            'cupo' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_group_rejects_nonexistent_subject(): void
    {
        $docenteId = $this->createTeacher(
            'DOC-101',
            'Ana',
            'Pérez',
        );

        $this->expectException(QueryException::class);

        DB::table('grupos_asignatura')->insert([
            'asignatura_id' => 999999,
            'docente_id' => $docenteId,
            'codigo_grupo' => '1',
            'cupo' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_group_rejects_negative_capacity(): void
    {
        $docenteId = $this->createTeacher(
            'DOC-102',
            'Luis',
            'Rojas',
        );

        $asignaturaId = $this->createSubject(
            'INF-102',
            'Programación II',
        );

        $this->expectException(QueryException::class);

        DB::table('grupos_asignatura')->insert([
            'asignatura_id' => $asignaturaId,
            'docente_id' => $docenteId,
            'codigo_grupo' => '1',
            'cupo' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_subject_code_must_be_unique(): void
    {
        $this->createSubject(
            'INF-201',
            'Base de Datos I',
        );

        $this->expectException(QueryException::class);

        $this->createSubject(
            'INF-201',
            'Otra asignatura',
        );
    }

    public function test_teacher_code_must_be_unique(): void
    {
        $this->createTeacher(
            'DOC-201',
            'Carlos',
            'Mendoza',
        );

        $this->expectException(QueryException::class);

        $this->createTeacher(
            'DOC-201',
            'María',
            'Flores',
        );
    }

    private function createTeacher(
        string $code,
        string $firstNames,
        string $lastNames,
    ): int {
        return DB::table('docentes')->insertGetId([
            'codigo_docente' => $code,
            'nombres' => $firstNames,
            'apellidos' => $lastNames,
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSubject(
        string $code,
        string $name,
    ): int {
        return DB::table('asignaturas')->insertGetId([
            'codigo' => $code,
            'nombre' => $name,
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
