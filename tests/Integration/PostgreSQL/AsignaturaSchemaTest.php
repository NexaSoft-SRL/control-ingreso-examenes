<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AsignaturaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_asignatura_can_have_multiple_groups_with_different_teachers(): void
    {
        $docenteA = $this->createTeacher(
            'DOC-001',
            'Ana',
            'Pérez',
        );

        $docenteB = $this->createTeacher(
            'DOC-002',
            'Luis',
            'Rojas',
        );

        $asignaturaId = DB::table('asignaturas')->insertGetId([
            'codigo' => 'INF-321',
            'nombre' => 'Redes de Computadoras',
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('grupos_asignatura')->insert([
            [
                'asignatura_id' => $asignaturaId,
                'docente_id' => $docenteA,
                'codigo_grupo' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'asignatura_id' => $asignaturaId,
                'docente_id' => $docenteB,
                'codigo_grupo' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame(
            2,
            DB::table('grupos_asignatura')
                ->where('asignatura_id', $asignaturaId)
                ->count()
        );

        $this->assertSame(
            2,
            DB::table('grupos_asignatura')
                ->where('asignatura_id', $asignaturaId)
                ->distinct()
                ->count('docente_id')
        );
    }

    public function test_teacher_can_be_responsible_for_multiple_groups(): void
    {
        $docenteId = $this->createTeacher(
            'DOC-003',
            'María',
            'Flores',
        );

        $asignaturaA = $this->createSubject(
            'INF-101',
            'Programación I',
        );

        $asignaturaB = $this->createSubject(
            'INF-202',
            'Base de Datos I',
        );

        DB::table('grupos_asignatura')->insert([
            [
                'asignatura_id' => $asignaturaA,
                'docente_id' => $docenteId,
                'codigo_grupo' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'asignatura_id' => $asignaturaA,
                'docente_id' => $docenteId,
                'codigo_grupo' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'asignatura_id' => $asignaturaB,
                'docente_id' => $docenteId,
                'codigo_grupo' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame(
            3,
            DB::table('grupos_asignatura')
                ->where('docente_id', $docenteId)
                ->count()
        );
    }

    public function test_group_code_is_unique_inside_the_same_subject(): void
    {
        $docenteId = $this->createTeacher(
            'DOC-004',
            'Carlos',
            'Mendoza',
        );

        $asignaturaId = $this->createSubject(
            'INF-303',
            'Sistemas Operativos',
        );

        DB::table('grupos_asignatura')->insert([
            'asignatura_id' => $asignaturaId,
            'docente_id' => $docenteId,
            'codigo_grupo' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('grupos_asignatura')->insert([
            'asignatura_id' => $asignaturaId,
            'docente_id' => $docenteId,
            'codigo_grupo' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
