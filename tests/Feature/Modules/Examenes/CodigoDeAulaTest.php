<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\AccesoAula;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El codigo de acceso de un aula (rutas 52 y 53): seis digitos, unico,
 * regenerable; lo ve y lo cambia cualquier docente del examen. Consultarlo
 * (52) no lo genera: eso lo hace solo la 53.
 */
final class CodigoDeAulaTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_consulting_the_code_does_not_generate_it(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula], [
            'fecha' => '2026-10-12',
            'hora_inicio' => '08:15:00',
        ]);
        $auxiliar = $this->usuarioConRol('Auxiliar', ['name' => 'Vargas Arce Sergio']);
        $this->ponerACargo($examen, $aula, $auxiliar);
        $cuenta = $this->cuenta($docente);
        $ruta = "/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo";

        for ($vez = 0; $vez < 2; $vez++) {
            $this->actingAs($cuenta)
                ->getJson($ruta)
                ->assertOk()
                ->assertExactJson(['data' => [
                    'codigo' => null,
                    'generado_en' => null,
                    'encargado' => 'Vargas Arce Sergio',
                    'examen' => '2026-10-12 08:15',
                ]]);
        }

        $this->assertDatabaseHas('examen_aula', ['examen_id' => $examen->id, 'aula_id' => $aula->id, 'codigo_acceso' => null]);
        $this->assertDatabaseCount('bitacora_operaciones', 0);

        $this->actingAs($cuenta)
            ->getJson("/api/examenes/{$examen->id}")
            ->assertJsonPath('data.aulas_detalle.0.usa_codigo', false);
    }

    public function test_the_code_is_generated_on_request_and_then_kept(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula], [
            'fecha' => '2026-10-12',
            'hora_inicio' => '08:15:00',
        ]);
        $auxiliar = $this->usuarioConRol('Auxiliar', ['name' => 'Vargas Arce Sergio']);
        $this->ponerACargo($examen, $aula, $auxiliar);
        $cuenta = $this->cuenta($docente);
        $ruta = "/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo";

        $respuesta = $this->actingAs($cuenta)
            ->postJson($ruta)
            ->assertOk()
            ->assertJsonPath('message', 'Código generado.')
            ->assertJsonPath('data.encargado', 'Vargas Arce Sergio')
            ->assertJsonPath('data.examen', '2026-10-12 08:15');

        $codigo = $respuesta->json('data.codigo');

        $this->assertIsString($codigo);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $codigo);
        $generadoEn = $respuesta->json('data.generado_en');

        $this->assertIsString($generadoEn);
        $this->assertStringEndsWith('-04:00', $generadoEn);
        $this->assertDatabaseHas('examen_aula', ['examen_id' => $examen->id, 'aula_id' => $aula->id, 'codigo_acceso' => $codigo]);

        $this->actingAs($cuenta)
            ->getJson($ruta)
            ->assertOk()
            ->assertJsonPath('data.codigo', $codigo)
            ->assertJsonPath('data.generado_en', $generadoEn);

        // El examen ya dice que esa aula usa codigo.
        $this->actingAs($cuenta)
            ->getJson("/api/examenes/{$examen->id}")
            ->assertJsonPath('data.aulas_detalle.0.usa_codigo', true);
    }

    public function test_regenerating_replaces_the_code(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);
        $cuenta = $this->cuenta($docente);
        $ruta = "/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo";

        $anterior = $this->actingAs($cuenta)->postJson($ruta)->json('data.codigo');

        $nuevo = $this->actingAs($cuenta)
            ->postJson($ruta)
            ->assertOk()
            ->assertJsonPath('message', 'Código regenerado.')
            ->assertJsonStructure(['data' => ['codigo', 'generado_en', 'encargado', 'examen']])
            ->json('data.codigo');

        $this->assertIsString($nuevo);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $nuevo);
        $this->assertNotSame($anterior, $nuevo);
        $this->assertDatabaseMissing('examen_aula', ['codigo_acceso' => $anterior]);
        $this->assertDatabaseHas('examen_aula', ['examen_id' => $examen->id, 'codigo_acceso' => $nuevo]);
    }

    public function test_regenerating_closes_the_accesses_opened_with_the_previous_code(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);
        $cuenta = $this->cuenta($docente);
        $ruta = "/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo";

        $this->actingAs($cuenta)->postJson($ruta)->assertOk();
        $asignada = ExamenAula::where('examen_id', $examen->id)->firstOrFail();

        // Un acceso abierto en este mismo segundo.
        $acceso = AccesoAula::create([
            'examen_aula_id' => $asignada->id,
            'nombre' => 'Vargas Arce Sergio',
            'creado_en' => now(),
        ]);

        $vale = static fn (): bool => AccesoAula::query()
            ->where('id', $acceso->id)
            ->whereIn('estado', ['PENDIENTE', 'AUTORIZADO'])
            ->exists();

        $this->assertTrue($vale());

        $this->actingAs($cuenta)->postJson($ruta)->assertOk();

        // El acceso no se borra (los ingresos lo nombran): deja de valer.
        $this->assertFalse($vale());
        $this->assertDatabaseHas('accesos_aula', ['id' => $acceso->id]);

        // Un acceso abierto despues con el codigo nuevo si vale.
        $this->travel(5)->seconds();

        $nuevo = AccesoAula::create([
            'examen_aula_id' => $asignada->id,
            'nombre' => 'Rojas Ledezma Andrea',
            'creado_en' => now(),
        ]);

        $this->assertDatabaseHas('accesos_aula', ['id' => $nuevo->id, 'estado' => 'PENDIENTE']);
        $this->assertDatabaseHas('accesos_aula', ['id' => $acceso->id, 'estado' => 'CORTADO']);
    }

    public function test_each_room_of_each_exam_has_its_own_code(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $codigos = [];

        for ($i = 0; $i < 4; $i++) {
            $aulas = [$this->aula(), $this->aula(), $this->aula()];
            $examen = $this->examen($docente, [$this->grupo($docente)], $aulas);

            foreach ($aulas as $aula) {
                $codigo = $this->actingAs($cuenta)
                    ->postJson("/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo")
                    ->assertOk()
                    ->json('data.codigo');

                $this->assertIsString($codigo);
                $codigos[] = $codigo;
            }
        }

        $this->assertCount(12, array_unique($codigos));
    }

    public function test_any_teacher_of_the_exam_handles_the_code_but_not_a_stranger(): void
    {
        $registra = $this->docente();
        $sumado = $this->docente();
        $ajeno = $this->docente();
        $asignatura = $this->asignatura();
        $aula = $this->aula();
        $examen = $this->examen($registra, [
            $this->grupo($registra, $asignatura),
            $this->grupo($sumado, $asignatura),
        ], [$aula]);
        $ruta = "/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo";

        $this->actingAs($this->cuenta($sumado))->getJson($ruta)->assertOk()->assertJsonPath('data.codigo', null);
        $this->actingAs($this->cuenta($sumado))->postJson($ruta)->assertOk();
        $this->actingAs($this->cuenta($sumado))->getJson($ruta)->assertOk()->assertJsonPath(
            'data.codigo',
            ExamenAula::where('examen_id', $examen->id)->value('codigo_acceso'),
        );

        $this->actingAs($this->cuenta($ajeno))
            ->getJson($ruta)
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este examen no está a su cargo.', 'alcance' => true]);

        $codigo = ExamenAula::where('examen_id', $examen->id)->value('codigo_acceso');

        $this->actingAs($this->cuenta($ajeno))
            ->postJson($ruta)
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertSame($codigo, ExamenAula::where('examen_id', $examen->id)->value('codigo_acceso'));
    }

    public function test_a_room_that_is_not_of_the_exam_is_not_found(): void
    {
        $docente = $this->docente();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$this->aula()]);
        $otraAula = $this->aula();
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->getJson("/api/examenes/{$examen->id}/aulas/{$otraAula->id}/codigo")
            ->assertNotFound()
            ->assertJsonPath('message', 'Examen o aula no encontrados.');

        $this->actingAs($cuenta)
            ->postJson("/api/examenes/{$examen->id}/aulas/{$otraAula->id}/codigo")
            ->assertNotFound();

        $this->actingAs($cuenta)
            ->getJson("/api/examenes/999999/aulas/{$otraAula->id}/codigo")
            ->assertNotFound();

        $this->actingAs($cuenta)
            ->getJson("/api/examenes/{$examen->id}/aulas/abc/codigo")
            ->assertNotFound();
    }

    public function test_generating_and_regenerating_are_recorded_in_the_audit_log(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);
        $cuenta = $this->cuenta($docente);
        $ruta = "/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo";
        $asignadaId = ExamenAula::where('examen_id', $examen->id)->value('id');

        $this->actingAs($cuenta)->postJson($ruta)->assertOk()->assertJsonPath('message', 'Código generado.');
        // Consultarlo no asienta nada.
        $this->actingAs($cuenta)->getJson($ruta)->assertOk();

        $this->assertDatabaseCount('bitacora_operaciones', 1);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'codigo_aula.generar',
            'tabla_afectada' => 'examen_aula',
            'registro_id' => $asignadaId,
            'usuario_id' => $cuenta->id,
        ]);

        $this->actingAs($cuenta)->postJson($ruta)->assertOk()->assertJsonPath('message', 'Código regenerado.');

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'codigo_aula.regenerar',
            'registro_id' => $asignadaId,
            'usuario_id' => $cuenta->id,
        ]);
    }

    public function test_the_code_needs_a_session_and_the_permission(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);
        $ruta = "/api/examenes/{$examen->id}/aulas/{$aula->id}/codigo";

        $this->getJson($ruta)->assertUnauthorized();
        $this->postJson($ruta)->assertUnauthorized();

        // El auxiliar a cargo del aula no tiene la pantalla de examenes.
        $auxiliar = $this->usuarioConRol('Auxiliar');
        $this->ponerACargo($examen, $aula, $auxiliar);

        $this->actingAs($auxiliar)->getJson($ruta)->assertForbidden()->assertJsonPath('permiso_requerido', 'examenes');
        $this->actingAs($auxiliar)->postJson($ruta)->assertForbidden()->assertJsonPath('permiso_requerido', 'examenes');

        $this->assertDatabaseHas('examen_aula', ['examen_id' => $examen->id, 'codigo_acceso' => null]);
    }
}
