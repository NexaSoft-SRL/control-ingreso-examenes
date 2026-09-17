<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use App\Modules\Examenes\Domain\Models\GrupoAsignatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AsignaturaModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_exposes_its_groups_and_their_teachers(): void
    {
        $docenteA = Docente::query()->create([
            'codigo_docente' => 'DOC-301',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'estado' => true,
        ]);

        $docenteB = Docente::query()->create([
            'codigo_docente' => 'DOC-302',
            'nombres' => 'Luis',
            'apellidos' => 'Rojas',
            'estado' => true,
        ]);

        $asignatura = Asignatura::query()->create([
            'codigo' => 'INF-321',
            'nombre' => 'Redes de Computadoras',
            'estado' => true,
        ]);

        GrupoAsignatura::query()->create([
            'asignatura_id' => $asignatura->id,
            'docente_id' => $docenteA->id,
            'codigo_grupo' => '1',
            'cupo' => 30,
        ]);

        GrupoAsignatura::query()->create([
            'asignatura_id' => $asignatura->id,
            'docente_id' => $docenteB->id,
            'codigo_grupo' => '2',
            'cupo' => 35,
        ]);

        $asignatura->load('grupos.docente');

        $this->assertCount(2, $asignatura->grupos);

        $grupo1 = $asignatura->grupos
            ->firstWhere('codigo_grupo', '1');

        $grupo2 = $asignatura->grupos
            ->firstWhere('codigo_grupo', '2');

        $this->assertInstanceOf(
            GrupoAsignatura::class,
            $grupo1
        );

        $this->assertInstanceOf(
            GrupoAsignatura::class,
            $grupo2
        );

        $docenteGrupo1 = $grupo1->docente;
        $docenteGrupo2 = $grupo2->docente;

        $this->assertInstanceOf(
            Docente::class,
            $docenteGrupo1
        );

        $this->assertInstanceOf(
            Docente::class,
            $docenteGrupo2
        );

        $this->assertSame(
            'DOC-301',
            $docenteGrupo1->codigo_docente
        );

        $this->assertSame(
            'DOC-302',
            $docenteGrupo2->codigo_docente
        );
    }

    public function test_teacher_exposes_multiple_assigned_groups(): void
    {
        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-303',
            'nombres' => 'María',
            'apellidos' => 'Flores',
            'estado' => true,
        ]);

        $asignatura = Asignatura::query()->create([
            'codigo' => 'INF-401',
            'nombre' => 'Ingeniería de Software',
            'estado' => true,
        ]);

        GrupoAsignatura::query()->create([
            'asignatura_id' => $asignatura->id,
            'docente_id' => $docente->id,
            'codigo_grupo' => '1',
            'cupo' => 25,
        ]);

        GrupoAsignatura::query()->create([
            'asignatura_id' => $asignatura->id,
            'docente_id' => $docente->id,
            'codigo_grupo' => '2',
            'cupo' => 25,
        ]);

        $docente->load('grupos');

        $this->assertCount(2, $docente->grupos);
    }
}
