<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Estudiantes\Domain\Enums\MotivoBaja;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El docente quita a un inscrito de su grupo con motivo. La inscripcion
 * queda dada de baja (no se borra) y deja de contar en todo el sistema.
 */
final class QuitarInscritoTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    // --- La baja ---

    public function test_removing_keeps_the_enrolment_with_who_when_and_why(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'RETIRO_MATERIA'])
            ->assertOk()
            ->assertExactJson(['message' => 'Estudiante quitado del grupo.']);

        $inscripcion = Inscripcion::query()->sole();

        $this->assertNotNull($inscripcion->baja_en);
        $this->assertSame($docente->user_id, $inscripcion->baja_por);
        $this->assertSame(MotivoBaja::RetiroMateria, $inscripcion->motivo_baja);
        $this->assertNull($inscripcion->detalle_baja);
        $this->assertSame(1, Estudiante::query()->count());
    }

    public function test_the_three_reasons_are_accepted_and_other_keeps_its_detail(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$uno, $dos, $tres] = $this->estudiantesInscritos($grupo, 3);

        $this->quitar($docente, $grupo, $uno, ['motivo' => 'RETIRO_MATERIA'])->assertOk();
        $this->quitar($docente, $grupo, $dos, ['motivo' => 'CARGADO_POR_ERROR'])->assertOk();
        $this->quitar($docente, $grupo, $tres, ['motivo' => 'OTRO', 'detalle' => 'Cambio de grupo autorizado'])->assertOk();

        $this->assertDatabaseHas('inscripciones', [
            'estudiante_id' => $tres->id,
            'motivo_baja' => 'OTRO',
            'detalle_baja' => 'Cambio de grupo autorizado',
        ]);
        $this->assertSame(3, Inscripcion::query()->whereNotNull('baja_en')->count());
    }

    public function test_the_reason_is_required_and_must_be_one_of_the_three(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $this->quitar($docente, $grupo, $estudiante, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo' => 'El motivo es obligatorio.']);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'ABANDONO'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo' => 'El motivo debe ser RETIRO_MATERIA, CARGADO_POR_ERROR u OTRO.']);

        $this->assertDatabaseHas('inscripciones', ['estudiante_id' => $estudiante->id, 'baja_en' => null]);
    }

    public function test_other_needs_a_detail_of_5_to_200_characters(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'OTRO'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['detalle' => 'El detalle es obligatorio cuando el motivo es Otro.']);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'OTRO', 'detalle' => 'Corto'.' '])
            ->assertOk();

        [$otro] = $this->estudiantesInscritos($grupo, 1);

        $this->quitar($docente, $grupo, $otro, ['motivo' => 'OTRO', 'detalle' => 'Nada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['detalle' => 'El detalle debe tener al menos 5 caracteres.']);

        $this->quitar($docente, $grupo, $otro, ['motivo' => 'OTRO', 'detalle' => str_repeat('x', 201)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['detalle' => 'El detalle no puede superar los 200 caracteres.']);
    }

    public function test_a_student_with_an_entry_in_an_exam_of_the_group_cannot_be_removed(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        $this->habilitar($examen, [$estudiante], $aula);
        $this->ingresar($examen, $estudiante, $aula);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'CARGADO_POR_ERROR'])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'No se puede quitar: ya tiene un ingreso registrado en un examen de este grupo.',
                'codigo' => 'CON_INGRESO',
            ]);

        $this->assertDatabaseHas('inscripciones', ['estudiante_id' => $estudiante->id, 'baja_en' => null]);
    }

    public function test_an_entry_in_an_exam_of_another_group_does_not_prevent_it(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $otroGrupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        Inscripcion::create(['estudiante_id' => $estudiante->id, 'grupo_id' => $otroGrupo->id, 'via' => 'ADMINISTRACION']);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$otroGrupo], [$aula]);
        $this->ingresar($examen, $estudiante, $aula);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'CARGADO_POR_ERROR'])->assertOk();
    }

    public function test_a_student_that_is_not_enrolled_or_was_already_removed_is_not_found(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $ajeno = $this->estudiante();

        $this->quitar($docente, $grupo, $ajeno, ['motivo' => 'CARGADO_POR_ERROR'])
            ->assertNotFound()
            ->assertJsonPath('message', 'El estudiante no está inscrito en este grupo.');

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'CARGADO_POR_ERROR'])->assertOk();
        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'CARGADO_POR_ERROR'])->assertNotFound();
    }

    // --- Acceso, alcance, periodo y bitacora ---

    public function test_a_teacher_cannot_remove_from_a_group_of_another_teacher(): void
    {
        $docente = $this->docente();
        $ajeno = $this->grupo($this->docente());
        [$estudiante] = $this->estudiantesInscritos($ajeno, 1);

        $this->quitar($docente, $ajeno, $estudiante, ['motivo' => 'CARGADO_POR_ERROR'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este grupo no está a su cargo.', 'alcance' => true]);

        $this->assertDatabaseHas('inscripciones', ['estudiante_id' => $estudiante->id, 'baja_en' => null]);
    }

    public function test_a_guest_or_a_role_without_the_permission_cannot_remove(): void
    {
        $grupo = $this->grupo($this->docente());
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $ruta = "/api/docente/grupos/{$grupo->id}/inscritos/{$estudiante->id}/baja";

        $this->postJson($ruta, ['motivo' => 'CARGADO_POR_ERROR'])->assertUnauthorized();

        $this->actingAs($this->administrador())
            ->postJson($ruta, ['motivo' => 'CARGADO_POR_ERROR'])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'mis_grupos');
    }

    public function test_a_group_that_does_not_exist_is_not_found(): void
    {
        $docente = $this->docente();
        $estudiante = $this->estudiante();

        $this->actingAs($this->cuentaDe($docente))
            ->postJson("/api/docente/grupos/999999/inscritos/{$estudiante->id}/baja", ['motivo' => 'OTRO', 'detalle' => 'Prueba'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Grupo no encontrado.');
    }

    public function test_a_group_of_a_closed_period_cannot_be_edited(): void
    {
        $docente = $this->docente();
        $cerrado = Periodo::create([
            'codigo' => '1/2020',
            'anio' => 2020,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => '2020-02-01',
            'fecha_fin' => '2020-07-01',
        ]);
        $grupo = $this->grupo($docente, null, null, $cerrado);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'CARGADO_POR_ERROR'])
            ->assertConflict()
            ->assertJsonPath('codigo', 'PERIODO_CERRADO');
    }

    public function test_removing_is_recorded_in_the_audit_log_with_the_reason(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, null, '4');
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'OTRO', 'detalle' => 'Cambio de grupo'])->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'inscritos.quitar',
            'usuario_id' => $docente->user_id,
            'tabla_afectada' => 'inscripciones',
            'registro_id' => Inscripcion::query()->value('id'),
        ]);

        $descripcion = DB::table('bitacora_operaciones')->where('operacion', 'inscritos.quitar')->value('descripcion');

        $this->assertIsString($descripcion);
        $this->assertStringContainsString($estudiante->codigo_universitario, $descripcion);
        $this->assertStringContainsString('grupo 4', $descripcion);
        $this->assertStringContainsString('Otro (Cambio de grupo)', $descripcion);
    }

    // --- Deja de contar en todo el sistema ---

    public function test_the_removed_student_leaves_the_group_list_its_counts_and_its_download(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$quitado, $sigue] = $this->estudiantesInscritos($grupo, 2);

        $this->quitar($docente, $grupo, $quitado, ['motivo' => 'CARGADO_POR_ERROR'])->assertOk();

        $sesion = $this->actingAs($this->cuentaDe($docente));

        $sesion->getJson("/api/docente/grupos/{$grupo->id}/inscritos")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $sigue->id);

        $sesion->getJson('/api/docente/grupos')
            ->assertOk()
            ->assertJsonPath('data.0.inscritos', 1)
            ->assertJsonPath('data.0.con_lista', true);

        $this->quitar($docente, $grupo, $sigue, ['motivo' => 'CARGADO_POR_ERROR'])->assertOk();

        $sesion->getJson('/api/docente/grupos')
            ->assertJsonPath('data.0.inscritos', 0)
            ->assertJsonPath('data.0.con_lista', false);

        $sesion->get("/api/docente/grupos/{$grupo->id}/inscritos/descarga", ['Accept' => 'application/json'])
            ->assertConflict()
            ->assertJsonPath('codigo', 'SIN_LISTA');
    }

    public function test_the_removed_student_leaves_the_exam_enabling_list_and_loses_its_condition_and_qr(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$quitado, $sigue] = $this->estudiantesInscritos($grupo, 2);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        $this->habilitar($examen, [$quitado, $sigue], $aula);
        DB::table('codigos_qr')->insert([
            'examen_id' => $examen->id,
            'estudiante_id' => $quitado->id,
            'token' => str_repeat('a', 64),
            'emitido_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->quitar($docente, $grupo, $quitado, ['motivo' => 'RETIRO_MATERIA'])->assertOk();

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.cifras.inscritos', 1);

        $this->assertDatabaseMissing('habilitaciones', ['estudiante_id' => $quitado->id]);
        $this->assertDatabaseMissing('codigos_qr', ['estudiante_id' => $quitado->id]);
        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $sigue->id]);
    }

    public function test_a_student_still_enrolled_in_another_group_of_the_exam_keeps_its_condition(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $otroGrupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        Inscripcion::create(['estudiante_id' => $estudiante->id, 'grupo_id' => $otroGrupo->id, 'via' => 'ADMINISTRACION']);
        $examen = $this->examen($docente, [$grupo, $otroGrupo]);
        $this->habilitar($examen, [$estudiante]);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'CARGADO_POR_ERROR'])->assertOk();

        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $estudiante->id]);
    }

    public function test_the_removed_student_leaves_the_roll_counts_and_the_student_record(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'CARGADO_POR_ERROR'])->assertOk();

        $administracion = $this->actingAs($this->administrador());

        $administracion->getJson('/api/estudiantes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $estudiante->id)
            ->assertJsonPath('data.0.grupos', 0);

        $administracion->getJson('/api/estudiantes/resumen')->assertJsonPath('inscripciones', 0);

        $administracion->getJson("/api/estudiantes/{$estudiante->id}")
            ->assertOk()
            ->assertJsonPath('data.materias', []);
    }

    public function test_the_removed_student_leaves_the_group_report(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$quitado, $sigue] = $this->estudiantesInscritos($grupo, 2);

        $this->quitar($docente, $grupo, $quitado, ['motivo' => 'CARGADO_POR_ERROR'])->assertOk();

        $respuesta = $this->actingAs($this->cuentaDe($docente))
            ->get("/api/reportes/grupos/{$grupo->id}/estudiantes")
            ->assertOk();

        $archivo = tempnam(sys_get_temp_dir(), 'reporte').'.xlsx';
        file_put_contents($archivo, (string) $respuesta->getContent());
        $codigos = array_column(IOFactory::load($archivo)->getActiveSheet()->toArray(), 0);
        unlink($archivo);

        $this->assertNotContains($quitado->codigo_universitario, $codigos);
        $this->assertContains($sigue->codigo_universitario, $codigos);
    }

    public function test_loading_the_file_again_enrolls_the_removed_student_again(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->cargarLista($docente, $grupo, ['202104821,7928194,Kevin René,Alvarado Claros'])->assertOk();
        $estudiante = Estudiante::query()->firstOrFail();

        $this->quitar($docente, $grupo, $estudiante, ['motivo' => 'CARGADO_POR_ERROR'])->assertOk();

        $this->cargarLista($docente, $grupo, ['202104821,7928194,Kevin René,Alvarado Claros'])
            ->assertOk()
            ->assertJsonPath('resumen.reutilizados', 1)
            ->assertJsonPath('resumen.ya_inscritos', 0);

        $inscripcion = Inscripcion::query()->sole();
        $this->assertNull($inscripcion->baja_en);
        $this->assertNull($inscripcion->motivo_baja);
        $this->assertNotNull($inscripcion->carga_id);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos")
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * @param  array<string, string>  $datos
     * @return TestResponse<JsonResponse>
     */
    private function quitar(Docente $docente, Grupo $grupo, Estudiante $estudiante, array $datos): TestResponse
    {
        return $this->actingAs($this->cuentaDe($docente))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos/{$estudiante->id}/baja", $datos);
    }
}
