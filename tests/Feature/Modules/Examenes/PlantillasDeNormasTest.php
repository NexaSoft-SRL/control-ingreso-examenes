<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\PlantillaNorma;
use Database\Seeders\NormasPredefinidasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las plantillas de normas: las predefinidas del sistema, que nadie edita,
 * y las propias de cada docente, que solo el ve, crea, edita y quita.
 */
final class PlantillasDeNormasTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    private const RUTA = '/api/normas/plantillas';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NormasPredefinidasSeeder::class);
    }

    private function predefinida(string $texto = 'Sin celular'): PlantillaNorma
    {
        return PlantillaNorma::whereNull('usuario_id')->where('texto', $texto)->firstOrFail();
    }

    private function propia(int $usuarioId, string $texto): PlantillaNorma
    {
        return PlantillaNorma::create(['usuario_id' => $usuarioId, 'texto' => $texto]);
    }

    public function test_the_list_shows_the_predefined_rules_and_the_own_templates_only(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $mia = $this->propia((int) $docente->user_id, 'Hoja de fórmulas A4');
        $this->propia((int) $otro->user_id, 'Con calculadora científica');

        $respuesta = $this->actingAs($this->cuenta($docente))
            ->getJson(self::RUTA)
            ->assertOk()
            ->assertJsonCount(count(NormasPredefinidasSeeder::NORMAS) + 1, 'data')
            ->assertJsonPath('data.0', [
                'id' => $this->predefinida(NormasPredefinidasSeeder::NORMAS[0])->id,
                'texto' => NormasPredefinidasSeeder::NORMAS[0],
                'predefinida' => true,
                'propia' => false,
            ])
            ->assertJsonPath('data.6', [
                'id' => $mia->id,
                'texto' => 'Hoja de fórmulas A4',
                'predefinida' => false,
                'propia' => true,
            ]);

        $textos = array_column((array) $respuesta->json('data'), 'texto');

        $this->assertSame([...NormasPredefinidasSeeder::NORMAS, 'Hoja de fórmulas A4'], $textos);
        $this->assertNotContains('Con calculadora científica', $textos);

        // El otro docente ve las predefinidas y la suya.
        $this->actingAs($this->cuenta($otro))
            ->getJson(self::RUTA)
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.6.texto', 'Con calculadora científica');
    }

    public function test_an_own_template_is_created_and_recorded(): void
    {
        $docente = $this->docente();

        $respuesta = $this->actingAs($this->cuenta($docente))
            ->postJson(self::RUTA, ['texto' => '  Hoja de   fórmulas A4 '])
            ->assertCreated()
            ->assertJsonPath('message', 'Plantilla creada.')
            ->assertJsonPath('data.texto', 'Hoja de fórmulas A4')
            ->assertJsonPath('data.predefinida', false)
            ->assertJsonPath('data.propia', true);

        $id = $respuesta->json('data.id');

        $this->assertIsInt($id);
        $this->assertDatabaseHas('plantillas_norma', [
            'id' => $id,
            'usuario_id' => $docente->user_id,
            'texto' => 'Hoja de fórmulas A4',
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'norma.plantilla_crear',
            'tabla_afectada' => 'plantillas_norma',
            'registro_id' => $id,
            'usuario_id' => $docente->user_id,
        ]);

        $this->actingAs($this->cuenta($docente))
            ->getJson(self::RUTA)
            ->assertJsonPath('data.6.id', $id);
    }

    public function test_a_template_without_text_or_out_of_range_is_rejected(): void
    {
        $cuenta = $this->cuenta($this->docente());

        foreach ([[], ['texto' => ''], ['texto' => '   '], ['texto' => null]] as $cuerpo) {
            $this->actingAs($cuenta)
                ->postJson(self::RUTA, $cuerpo)
                ->assertUnprocessable()
                ->assertJsonPath('errors.texto.0', 'Obligatorio');
        }

        foreach (['ab', str_repeat('a', 301)] as $texto) {
            $this->actingAs($cuenta)
                ->postJson(self::RUTA, ['texto' => $texto])
                ->assertUnprocessable()
                ->assertJsonPath('errors.texto.0', 'Entre 3 y 300 caracteres');
        }

        $this->actingAs($cuenta)
            ->postJson(self::RUTA, ['texto' => str_repeat('a', 300)])
            ->assertCreated();

        $this->assertSame(1, PlantillaNorma::whereNotNull('usuario_id')->count());
    }

    public function test_a_text_the_teacher_already_has_is_rejected_ignoring_case_and_accents(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $this->propia((int) $docente->user_id, 'Hoja de fórmulas A4');

        foreach (['Hoja de fórmulas A4', 'HOJA DE FORMULAS a4', ' hoja  de formulas a4 '] as $texto) {
            $this->actingAs($this->cuenta($docente))
                ->postJson(self::RUTA, ['texto' => $texto])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Ya existe')
                ->assertJsonPath('errors.texto.0', 'Ya existe');
        }

        $this->assertSame(1, PlantillaNorma::where('usuario_id', $docente->user_id)->count());
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'norma.plantilla_crear']);

        // El mismo texto sirve a otro docente: las plantillas son de cada uno.
        $this->actingAs($this->cuenta($otro))
            ->postJson(self::RUTA, ['texto' => 'Hoja de fórmulas A4'])
            ->assertCreated();
    }

    public function test_an_own_template_is_edited_and_recorded(): void
    {
        $docente = $this->docente();
        $plantilla = $this->propia((int) $docente->user_id, 'Hoja de fórmulas A4');
        $this->propia((int) $docente->user_id, 'Con calculadora científica');
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->putJson(self::RUTA."/{$plantilla->id}", ['texto' => 'Hoja de fórmulas A4 manuscrita'])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $plantilla->id,
                    'texto' => 'Hoja de fórmulas A4 manuscrita',
                    'predefinida' => false,
                    'propia' => true,
                ],
                'message' => 'Plantilla guardada.',
            ]);

        $this->assertDatabaseHas('plantillas_norma', ['id' => $plantilla->id, 'texto' => 'Hoja de fórmulas A4 manuscrita']);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'norma.plantilla_editar',
            'tabla_afectada' => 'plantillas_norma',
            'registro_id' => $plantilla->id,
            'usuario_id' => $docente->user_id,
        ]);

        // Guardarla con su mismo texto no choca consigo misma.
        $this->actingAs($cuenta)
            ->putJson(self::RUTA."/{$plantilla->id}", ['texto' => 'hoja de formulas a4 manuscrita'])
            ->assertOk();

        // Con el texto de otra suya, si.
        $this->actingAs($cuenta)
            ->putJson(self::RUTA."/{$plantilla->id}", ['texto' => 'con calculadora cientifica'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.texto.0', 'Ya existe');

        $this->actingAs($cuenta)
            ->putJson(self::RUTA."/{$plantilla->id}", ['texto' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.texto.0', 'Obligatorio');

        $this->actingAs($cuenta)
            ->putJson(self::RUTA.'/999999', ['texto' => 'Otra norma'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Plantilla no encontrada.');
    }

    public function test_an_own_template_is_removed_and_recorded(): void
    {
        $docente = $this->docente();
        $plantilla = $this->propia((int) $docente->user_id, 'Hoja de fórmulas A4');
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->deleteJson(self::RUTA."/{$plantilla->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('plantillas_norma', ['id' => $plantilla->id]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'norma.plantilla_quitar',
            'tabla_afectada' => 'plantillas_norma',
            'registro_id' => $plantilla->id,
            'usuario_id' => $docente->user_id,
        ]);

        $this->actingAs($cuenta)
            ->deleteJson(self::RUTA."/{$plantilla->id}")
            ->assertNotFound();

        // Quitada, el texto vuelve a estar libre.
        $this->actingAs($cuenta)
            ->postJson(self::RUTA, ['texto' => 'Hoja de fórmulas A4'])
            ->assertCreated();
    }

    public function test_predefined_rules_are_not_edited_nor_removed(): void
    {
        $cuenta = $this->cuenta($this->docente());
        $predefinida = $this->predefinida();

        $this->actingAs($cuenta)
            ->putJson(self::RUTA."/{$predefinida->id}", ['texto' => 'Con celular'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Las normas predefinidas no se modifican.', 'alcance' => true]);

        $this->actingAs($cuenta)
            ->deleteJson(self::RUTA."/{$predefinida->id}")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseHas('plantillas_norma', ['id' => $predefinida->id, 'texto' => 'Sin celular']);
        $this->assertSame(6, PlantillaNorma::whereNull('usuario_id')->count());
    }

    public function test_the_template_of_another_teacher_is_not_edited_nor_removed(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $ajena = $this->propia((int) $otro->user_id, 'Con calculadora científica');
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->putJson(self::RUTA."/{$ajena->id}", ['texto' => 'Sin calculadora'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Esta plantilla no es tuya.', 'alcance' => true]);

        $this->actingAs($cuenta)
            ->deleteJson(self::RUTA."/{$ajena->id}")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseHas('plantillas_norma', ['id' => $ajena->id, 'texto' => 'Con calculadora científica']);
        $this->assertDatabaseMissing('bitacora_operaciones', ['tabla_afectada' => 'plantillas_norma']);
    }

    public function test_templates_need_a_session_and_the_permission(): void
    {
        $docente = $this->docente();
        $plantilla = $this->propia((int) $docente->user_id, 'Hoja de fórmulas A4');
        $sinPermiso = $this->usuarioConRol('Auxiliar');

        foreach ([
            ['GET', self::RUTA],
            ['POST', self::RUTA],
            ['PUT', self::RUTA."/{$plantilla->id}"],
            ['DELETE', self::RUTA."/{$plantilla->id}"],
        ] as [$metodo, $ruta]) {
            $this->json($metodo, $ruta, ['texto' => 'Otra norma'])->assertUnauthorized();
        }

        foreach ([
            ['GET', self::RUTA],
            ['POST', self::RUTA],
            ['PUT', self::RUTA."/{$plantilla->id}"],
            ['DELETE', self::RUTA."/{$plantilla->id}"],
        ] as [$metodo, $ruta]) {
            $this->actingAs($sinPermiso)
                ->json($metodo, $ruta, ['texto' => 'Otra norma'])
                ->assertForbidden()
                ->assertJsonPath('permiso_requerido', 'examenes');
        }

        $this->assertDatabaseHas('plantillas_norma', ['id' => $plantilla->id, 'texto' => 'Hoja de fórmulas A4']);
    }
}
