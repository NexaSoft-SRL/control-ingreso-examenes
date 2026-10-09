<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\ExamenNorma;
use App\Modules\Examenes\Domain\Models\PlantillaNorma;
use Database\Seeders\NormasPredefinidasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las normas del examen: las marcadas de plantillas (predefinidas o
 * propias) mas el texto libre. El examen guarda una copia del texto de cada
 * norma marcada: editar o quitar la plantilla no lo cambia.
 */
final class NormasDelExamenTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NormasPredefinidasSeeder::class);
    }

    private function predefinida(string $texto): PlantillaNorma
    {
        return PlantillaNorma::whereNull('usuario_id')->where('texto', $texto)->firstOrFail();
    }

    private function propia(int $usuarioId, string $texto): PlantillaNorma
    {
        return PlantillaNorma::create(['usuario_id' => $usuarioId, 'texto' => $texto]);
    }

    /**
     * @return list<array{plantilla_id: int|null, texto: string}>
     */
    private function marcadas(int $examenId): array
    {
        $marcadas = [];

        foreach (ExamenNorma::where('examen_id', $examenId)->orderBy('orden')->get() as $norma) {
            $marcadas[] = ['plantilla_id' => $norma->plantilla_id, 'texto' => $norma->texto];
        }

        return $marcadas;
    }

    public function test_marked_rules_and_free_text_are_saved_with_the_exam_and_shown_again(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $celular = $this->predefinida('Sin celular');
        $documento = $this->predefinida('Documento de identidad a la vista');
        $formulas = $this->propia((int) $docente->user_id, 'Hoja de fórmulas A4');
        $cuenta = $this->cuenta($docente);

        $respuesta = $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'normas' => 'Se entrega la hoja de fórmulas al terminar.',
                'normas_marcadas' => [$formulas->id, $celular->id, $documento->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.normas', 'Se entrega la hoja de fórmulas al terminar.')
            ->assertJsonCount(3, 'data.normas_marcadas')
            ->assertJsonPath('data.normas_marcadas.0.plantilla_id', $formulas->id)
            ->assertJsonPath('data.normas_marcadas.0.texto', 'Hoja de fórmulas A4')
            ->assertJsonPath('data.normas_marcadas.1.plantilla_id', $celular->id)
            ->assertJsonPath('data.normas_marcadas.1.texto', 'Sin celular')
            ->assertJsonPath('data.normas_marcadas.2.plantilla_id', $documento->id);

        $examenId = $respuesta->json('data.id');
        $this->assertIsInt($examenId);
        $this->assertIsInt($respuesta->json('data.normas_marcadas.0.id'));

        $this->assertSame([
            ['plantilla_id' => $formulas->id, 'texto' => 'Hoja de fórmulas A4'],
            ['plantilla_id' => $celular->id, 'texto' => 'Sin celular'],
            ['plantilla_id' => $documento->id, 'texto' => 'Documento de identidad a la vista'],
        ], $this->marcadas($examenId));

        // Al abrirlo vuelven lo marcado y lo escrito.
        $this->actingAs($cuenta)
            ->getJson("/api/examenes/{$examenId}")
            ->assertOk()
            ->assertJsonPath('data.normas', 'Se entrega la hoja de fórmulas al terminar.')
            ->assertJsonPath('data.normas_marcadas.0.texto', 'Hoja de fórmulas A4')
            ->assertJsonPath('data.normas_marcadas.2.texto', 'Documento de identidad a la vista');

        // El listado no las trae.
        $listado = $this->actingAs($cuenta)->getJson('/api/examenes')->assertOk();
        $this->assertArrayNotHasKey('normas_marcadas', (array) $listado->json('data.0'));
    }

    public function test_marking_no_rule_is_valid(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['normas' => null]))
            ->assertCreated()
            ->assertJsonPath('data.normas', null)
            ->assertJsonPath('data.normas_marcadas', []);

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'tipo' => 'FINAL',
                'normas' => '',
                'normas_marcadas' => [],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.normas_marcadas', []);

        $this->assertDatabaseCount('examen_norma', 0);
    }

    public function test_a_template_of_another_teacher_or_unknown_cannot_be_marked(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $ajena = $this->propia((int) $otro->user_id, 'Con calculadora científica');
        $celular = $this->predefinida('Sin celular');
        $cuenta = $this->cuenta($docente);

        foreach ([[$celular->id, $ajena->id], [999999]] as $marcadas) {
            $this->actingAs($cuenta)
                ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['normas_marcadas' => $marcadas]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.normas_marcadas.0', 'La norma no existe.');
        }

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'normas_marcadas' => [$celular->id, $celular->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['normas_marcadas.0' => 'Hay una norma repetida.']);

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['normas_marcadas' => ['x']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['normas_marcadas.0' => 'La norma no es válida.']);

        $this->assertDatabaseCount('examenes', 0);
        $this->assertDatabaseCount('examen_norma', 0);
    }

    public function test_editing_a_template_does_not_change_the_exams_already_registered(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $formulas = $this->propia((int) $docente->user_id, 'Hoja de fórmulas A4');
        $cuenta = $this->cuenta($docente);

        $anterior = $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['normas_marcadas' => [$formulas->id]]))
            ->assertCreated()
            ->json('data.id');
        $this->assertIsInt($anterior);

        $this->actingAs($cuenta)
            ->putJson("/api/normas/plantillas/{$formulas->id}", ['texto' => 'Hoja de fórmulas carta'])
            ->assertOk();

        // El examen ya registrado conserva la norma con que se guardo.
        $this->actingAs($cuenta)
            ->getJson("/api/examenes/{$anterior}")
            ->assertJsonPath('data.normas_marcadas.0.plantilla_id', $formulas->id)
            ->assertJsonPath('data.normas_marcadas.0.texto', 'Hoja de fórmulas A4');

        // Tambien si despues se modifica otro dato del examen.
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$anterior}", $this->cuerpo($asignatura, [$grupo], [], [
                'hora_inicio' => '10:15',
                'normas_marcadas' => [$formulas->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.hora', '10:15')
            ->assertJsonPath('data.normas_marcadas.0.texto', 'Hoja de fórmulas A4');

        // El cambio rige para los que se registren despues.
        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'tipo' => 'FINAL',
                'normas_marcadas' => [$formulas->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.normas_marcadas.0.texto', 'Hoja de fórmulas carta');
    }

    public function test_removing_a_template_keeps_the_rule_in_the_exams_already_registered(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $formulas = $this->propia((int) $docente->user_id, 'Hoja de fórmulas A4');
        $celular = $this->predefinida('Sin celular');
        $cuenta = $this->cuenta($docente);

        $examenId = $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'normas_marcadas' => [$formulas->id, $celular->id],
            ]))
            ->assertCreated()
            ->json('data.id');
        $this->assertIsInt($examenId);

        $this->actingAs($cuenta)
            ->deleteJson("/api/normas/plantillas/{$formulas->id}")
            ->assertNoContent();

        $detalle = $this->actingAs($cuenta)
            ->getJson("/api/examenes/{$examenId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.normas_marcadas')
            ->assertJsonPath('data.normas_marcadas.0.plantilla_id', null)
            ->assertJsonPath('data.normas_marcadas.0.texto', 'Hoja de fórmulas A4')
            ->assertJsonPath('data.normas_marcadas.1.plantilla_id', $celular->id);

        $suelta = $detalle->json('data.normas_marcadas.0.id');
        $this->assertIsInt($suelta);

        // Guardar el examen sin nombrarla la deja donde esta.
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($asignatura, [$grupo], [], [
                'normas_marcadas' => [$celular->id],
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.normas_marcadas');

        $this->assertSame([
            ['plantilla_id' => $celular->id, 'texto' => 'Sin celular'],
            ['plantilla_id' => null, 'texto' => 'Hoja de fórmulas A4'],
        ], $this->marcadas($examenId));

        // Con `normas_conservadas` se dice cuales siguen.
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($asignatura, [$grupo], [], [
                'normas_marcadas' => [$celular->id],
                'normas_conservadas' => [$suelta],
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.normas_marcadas');

        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($asignatura, [$grupo], [], [
                'normas_marcadas' => [$celular->id],
                'normas_conservadas' => [],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.normas_marcadas')
            ->assertJsonPath('data.normas_marcadas.0.texto', 'Sin celular');
    }

    public function test_modifying_the_exam_replaces_the_marked_rules(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $celular = $this->predefinida('Sin celular');
        $apuntes = $this->predefinida('Sin apuntes ni libros');
        $formulas = $this->propia((int) $docente->user_id, 'Hoja de fórmulas A4');
        $cuenta = $this->cuenta($docente);

        $examenId = $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'normas_marcadas' => [$celular->id, $apuntes->id],
            ]))
            ->assertCreated()
            ->json('data.id');
        $this->assertIsInt($examenId);

        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($asignatura, [$grupo], [], [
                'normas' => 'Texto nuevo.',
                'normas_marcadas' => [$formulas->id, $celular->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.normas', 'Texto nuevo.');

        $this->assertSame([
            ['plantilla_id' => $formulas->id, 'texto' => 'Hoja de fórmulas A4'],
            ['plantilla_id' => $celular->id, 'texto' => 'Sin celular'],
        ], $this->marcadas($examenId));

        // Sin `normas_marcadas` no queda ninguna marcada.
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($asignatura, [$grupo]))
            ->assertOk()
            ->assertJsonPath('data.normas_marcadas', []);

        $this->assertDatabaseCount('examen_norma', 0);
    }

    public function test_with_entries_the_marked_rules_and_the_free_text_can_still_change(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $this->habilitar($examen, [$estudiante], $aula);
        $this->ingresar($examen, $estudiante, $aula);
        $celular = $this->predefinida('Sin celular');

        $this->actingAs($this->cuenta($docente))
            ->putJson("/api/examenes/{$examen->id}", $this->cuerpo($asignatura, [$grupo], [$aula], [
                'tipo' => $examen->tipo->value,
                'fecha' => $examen->fecha->format('Y-m-d'),
                'hora_inicio' => substr($examen->hora_inicio, 0, 5),
                'duracion_minutos' => $examen->duracion_minutos,
                'normas' => 'Normas nuevas.',
                'normas_marcadas' => [$celular->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.normas', 'Normas nuevas.')
            ->assertJsonPath('data.normas_marcadas.0.texto', 'Sin celular');

        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_deleting_the_exam_takes_its_marked_rules_and_leaves_the_templates(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo]);
        $celular = $this->predefinida('Sin celular');
        ExamenNorma::create(['examen_id' => $examen->id, 'plantilla_id' => $celular->id, 'texto' => $celular->texto, 'orden' => 1]);

        $this->actingAs($this->cuenta($docente))
            ->deleteJson("/api/examenes/{$examen->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('examen_norma', 0);
        $this->assertDatabaseHas('plantillas_norma', ['id' => $celular->id]);
        $this->assertSame(0, Examen::count());
    }

    public function test_the_teacher_of_an_included_group_sees_the_rules_of_the_exam(): void
    {
        $registra = $this->docente();
        $sumado = $this->docente();
        $asignatura = $this->asignatura();
        $examen = $this->examen($registra, [
            $this->grupo($registra, $asignatura),
            $this->grupo($sumado, $asignatura),
        ]);
        $formulas = $this->propia((int) $registra->user_id, 'Hoja de fórmulas A4');
        ExamenNorma::create(['examen_id' => $examen->id, 'plantilla_id' => $formulas->id, 'texto' => $formulas->texto, 'orden' => 1]);

        // Ve la norma del examen, pero la plantilla sigue sin ser suya.
        $this->actingAs($this->cuenta($sumado))
            ->getJson("/api/examenes/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.propio', false)
            ->assertJsonPath('data.normas_marcadas.0.texto', 'Hoja de fórmulas A4');

        $this->actingAs($this->cuenta($sumado))
            ->getJson('/api/normas/plantillas')
            ->assertJsonCount(6, 'data');
    }
}
