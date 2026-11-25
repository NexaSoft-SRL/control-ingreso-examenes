<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aviso del paso de grupos: cuales de los grupos elegidos ya tienen un examen
 * de ese tipo. El registro lo rechaza igual; asi se dice antes de llegar al final.
 */
final class GruposConExamenDeTipoTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function ocupados(array $parametros): string
    {
        return '/api/examenes/opciones/ocupados?'.http_build_query($parametros);
    }

    public function test_it_names_the_groups_that_already_have_an_exam_of_that_type(): void
    {
        $docente = $this->docente();
        $calculo = $this->grupo($docente, $this->asignatura('Cálculo I'));
        $fisica = $this->grupo($docente, $this->asignatura('Física I'));
        $this->examen($docente, [$calculo], [$this->aula()], ['fecha' => $this->diaHabil(3), 'tipo' => 'PRIMER_PARCIAL']);

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->ocupados(['grupos' => [$calculo->id, $fisica->id], 'tipo' => 'PRIMER_PARCIAL']))
            ->assertOk()
            ->assertExactJson(['ocupados' => [[
                'grupo_id' => $calculo->id,
                'codigo' => $calculo->codigo,
                'mensaje' => "El grupo {$calculo->codigo} ya tiene un primer parcial.",
            ]]]);
    }

    public function test_another_type_or_the_own_exam_does_not_count(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura('Cálculo I'));
        $examen = $this->examen($docente, [$grupo], [$this->aula()], ['fecha' => $this->diaHabil(3), 'tipo' => 'PRIMER_PARCIAL']);
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->getJson($this->ocupados(['grupos' => [$grupo->id], 'tipo' => 'SEGUNDO_PARCIAL']))
            ->assertOk()
            ->assertExactJson(['ocupados' => []]);

        $this->actingAs($cuenta)
            ->getJson($this->ocupados(['grupos' => [$grupo->id], 'tipo' => 'PRIMER_PARCIAL', 'examen_id' => $examen->id]))
            ->assertOk()
            ->assertExactJson(['ocupados' => []]);
    }

    public function test_the_parameters_are_validated_and_a_session_is_required(): void
    {
        $this->getJson($this->ocupados(['grupos' => [1], 'tipo' => 'PRIMER_PARCIAL']))->assertUnauthorized();

        $docente = $this->docente();
        $this->actingAs($this->cuenta($docente))
            ->getJson($this->ocupados(['tipo' => 'PRIMER_PARCIAL']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupos');
        $this->actingAs($this->cuenta($docente))
            ->getJson($this->ocupados(['grupos' => [1], 'tipo' => 'OTRO']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo');
    }
}
