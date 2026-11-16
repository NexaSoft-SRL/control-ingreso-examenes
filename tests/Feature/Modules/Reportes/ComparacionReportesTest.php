<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ComparacionReportesTest extends TestCase
{
    use EscenarioDeReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::AHORA);
    }

    public function test_a_teacher_can_compare_their_exams_and_periods(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $anterior = $this->periodo('1/2026', '2026-02-01', '2026-07-31', TipoPeriodo::Semestre1);
        $actual = $this->periodo('2/2026', '2026-08-01', '2026-12-31', TipoPeriodo::Semestre2);
        $grupoAnterior = $this->grupo($docente, $this->asignatura('Programación'), periodo: $anterior);
        $grupoActual = $this->grupo($docente, $this->asignatura('Cálculo'), periodo: $actual);
        $examenAnterior = $this->examen($docente, [$grupoAnterior], [], ['fecha' => '2026-06-10']);
        $examenActual = $this->examen($docente, [$grupoActual]);
        $aula = $this->aula();

        $estudiantesAnteriores = $this->estudiantesInscritos($grupoAnterior, 4);
        $this->habilitar($examenAnterior, array_slice($estudiantesAnteriores, 0, 3), $aula);
        $this->ingresar($examenAnterior, $estudiantesAnteriores[0], $aula);
        $this->ingresar($examenAnterior, $estudiantesAnteriores[1], $aula);

        $estudiantesActuales = $this->estudiantesInscritos($grupoActual, 2);
        $this->habilitar($examenActual, $estudiantesActuales, $aula);
        $this->ingresar($examenActual, $estudiantesActuales[0], $aula);

        $respuesta = $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/reportes/comparacion?examenes[]='.$examenAnterior->id.'&examenes[]='.$examenActual->id
                .'&periodos[]='.$anterior->id.'&periodos[]='.$actual->id)
            ->assertOk()
            ->assertJsonPath('examenes.0.id', $examenAnterior->id)
            ->assertJsonPath('examenes.0.asistencia', 67)
            ->assertJsonPath('examenes.0.ausentes', 1)
            ->assertJsonPath('examenes.1.id', $examenActual->id)
            ->assertJsonPath('examenes.1.asistencia', 50)
            ->assertJsonPath('periodos.0.codigo', '1/2026')
            ->assertJsonPath('periodos.0.examenes', 1)
            ->assertJsonPath('periodos.0.asistencia', 67)
            ->assertJsonPath('periodos.1.codigo', '2/2026')
            ->assertJsonPath('periodos.1.asistencia', 50);

        $examenes = $respuesta->json('examenes');
        $periodos = $respuesta->json('periodos');
        self::assertIsArray($examenes);
        self::assertIsArray($periodos);
        self::assertCount(2, $examenes);
        self::assertCount(2, $periodos);
    }

    public function test_comparison_options_only_include_the_teachers_exams(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo]);
        $otroDocente = $this->docente('Otro Docente');
        $otroExamen = $this->examen($otroDocente, [$this->grupo($otroDocente)]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/reportes/comparacion/opciones')
            ->assertOk()
            ->assertJsonCount(1, 'examenes')
            ->assertJsonPath('examenes.0.id', $examen->id)
            ->assertJsonMissing(['id' => $otroExamen->id]);
    }

    public function test_a_teacher_cannot_compare_an_exam_outside_their_scope(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $ajeno = $this->docente('Otro Docente');
        $examenAjeno = $this->examen($ajeno, [$this->grupo($ajeno)]);
        $propio = $this->examen($docente, [$this->grupo($docente)]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/reportes/comparacion?examenes[]='.$propio->id.'&examenes[]='.$examenAjeno->id)
            ->assertForbidden()
            ->assertJsonPath('alcance', true);
    }

    private function periodo(string $codigo, string $inicio, string $fin, TipoPeriodo $tipo): Periodo
    {
        return Periodo::create([
            'codigo' => $codigo,
            'anio' => 2026,
            'numero' => $tipo === TipoPeriodo::Semestre1 ? 1 : 2,
            'tipo' => $tipo,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
        ]);
    }
}
