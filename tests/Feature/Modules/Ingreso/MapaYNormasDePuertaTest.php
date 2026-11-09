<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las normas marcadas en la cabecera de la puerta (ruta 63) y los
 * edificios para el mapa de «Otra aula», que tambien alcanza quien entro
 * con codigo de aula.
 */
final class MapaYNormasDePuertaTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    private function mapa(?int $aulaId = null): string
    {
        return '/api/puerta/aulas/'.($aulaId ?? $this->aula->id).'/edificios';
    }

    public function test_the_door_shows_the_marked_rules_of_each_exam_in_order(): void
    {
        $this->examen->update(['normas' => 'Hoja de fórmulas A4.']);

        DB::table('examen_norma')->insert([
            ['examen_id' => $this->examen->id, 'plantilla_id' => null, 'texto' => 'Sin celular', 'orden' => 2],
            ['examen_id' => $this->examen->id, 'plantilla_id' => null, 'texto' => 'Documento a la vista', 'orden' => 1],
        ]);

        $otroGrupo = $this->grupo($this->docente, $this->asignatura('Cálculo I'), '1');
        $sinNormas = $this->examen($this->docente, [$otroGrupo], [$this->aula]);

        $this->actingAs($this->cuentaDocente)
            ->getJson('/api/puerta/aulas/'.$this->aula->id)
            ->assertOk()
            ->assertJsonPath('examenes.0.id', $this->examen->id)
            ->assertJsonPath('examenes.0.normas', 'Hoja de fórmulas A4.')
            ->assertJsonPath('examenes.0.normas_marcadas', ['Documento a la vista', 'Sin celular'])
            ->assertJsonPath('examenes.1.id', $sinNormas->id)
            ->assertJsonPath('examenes.1.normas', null)
            ->assertJsonPath('examenes.1.normas_marcadas', []);
    }

    public function test_the_door_map_brings_the_buildings_of_the_faculties_of_its_exams(): void
    {
        $economia = $this->edificio($this->facultad('fce'), 'Edificio de Economía');
        $this->sumarAula($this->aula('E-12', $economia, '2° Piso'));
        $ajeno = $this->edificio($this->facultad('fach'), 'Edificio de Arquitectura');

        $respuesta = $this->actingAs($this->cuentaDocente)
            ->getJson($this->mapa())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $economia->id)
            ->assertJsonPath('data.0.facultad', 'FCE')
            ->assertJsonPath('data.1.nombre', 'Edificio Académico 2')
            ->assertJsonPath('data.1.facultad', 'FCyT')
            ->assertJsonPath('data.1.aulas', ['691A'])
            ->assertJsonStructure([
                'data' => [['id', 'clave', 'facultad', 'nombre', 'poligono', 'centro', 'aulas', 'pisos']],
                'meta' => ['caja' => ['lon', 'lat']],
            ]);

        $this->assertNotContains($ajeno->id, array_column((array) $respuesta->json('data'), 'id'));
    }

    public function test_an_access_with_room_code_reads_the_map_of_its_room_only(): void
    {
        $this->ponerCodigoDeAula('482913');
        $otra = $this->aula('617');
        $this->sumarAula($otra);

        // Sin cuenta no se leen los edificios del sistema.
        $this->getJson('/api/edificios')->assertStatus(401);
        $this->getJson($this->mapa())->assertStatus(401);

        $this->postJson('/api/auth/codigo-aula', ['codigo' => '482913', 'nombre' => 'Vargas Arce Sergio'])
            ->assertOk();
        // El docente lo autoriza en persona.
        DB::table('accesos_aula')->update(['estado' => 'AUTORIZADO']);

        $this->getJson($this->mapa())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Edificio Académico 2');

        $this->getJson($this->mapa($otra->id))
            ->assertStatus(403)
            ->assertJsonPath('alcance', true);

        $this->getJson('/api/edificios')->assertStatus(401);
    }

    public function test_the_door_map_respects_the_permission_and_the_scope(): void
    {
        $this->actingAs(UserFactory::new()->createOne())
            ->getJson($this->mapa())
            ->assertStatus(403)
            ->assertJsonPath('permiso_requerido', 'punto_control');

        $this->actingAs($this->cuentaAuxiliar())
            ->getJson($this->mapa())
            ->assertStatus(403)
            ->assertJsonPath('alcance', true);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->mapa(999999))
            ->assertStatus(404);
    }

    public function test_the_door_names_an_exam_of_the_fifth_type(): void
    {
        $this->examen->update(['tipo' => 'MESA']);

        $this->actingAs($this->cuentaDocente)
            ->getJson('/api/puerta/aulas/'.$this->aula->id)
            ->assertOk()
            ->assertJsonPath('examenes.0.tipo', 'Examen de mesa');
    }
}
