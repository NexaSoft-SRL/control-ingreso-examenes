<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La correccion de los datos de un estudiante: el docente a un inscrito de
 * su grupo, la administracion a cualquiera. El estudiante existe una sola vez
 * en el padron: el cambio vale en todos sus grupos. Queda en la bitacora.
 */
final class EditarEstudianteTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const DATOS = [
        'documento' => '5003748-1A',
        'nombres' => 'Rodrigo',
        'apellidos' => 'Velasquez Ricaldez',
    ];

    /**
     * @param  array<string, string>  $datos
     * @return TestResponse<Response>
     */
    private function editar(Docente $docente, Grupo $grupo, Estudiante $estudiante, array $datos = self::DATOS): TestResponse
    {
        return $this->actingAs($this->cuentaDe($docente))
            ->putJson("/api/docente/grupos/{$grupo->id}/inscritos/{$estudiante->id}", $datos);
    }

    public function test_a_teacher_corrects_a_student_of_their_group_and_it_is_logged(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $estudiante->forceFill(['documento_identidad' => null])->save();

        $this->editar($docente, $grupo, $estudiante)
            ->assertOk()
            ->assertExactJson(['message' => 'Estudiante actualizado.']);

        $estudiante->refresh();
        $this->assertSame('5003748-1A', $estudiante->documento_identidad);
        $this->assertSame('Rodrigo', $estudiante->nombres);
        $this->assertSame('Velasquez Ricaldez', $estudiante->apellidos);

        $descripcion = DB::table('bitacora_operaciones')
            ->where('operacion', 'estudiante.editar')
            ->where('registro_id', $estudiante->id)
            ->where('usuario_id', $docente->user_id)
            ->value('descripcion');
        $this->assertIsString($descripcion);
        $this->assertStringContainsString('C.I.: «—» → «5003748-1A»', $descripcion);
    }

    public function test_the_change_is_seen_in_every_group_of_the_student(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $otroGrupo = $this->grupo($this->docente());
        DB::table('inscripciones')->insert([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $otroGrupo->id,
            'via' => 'DOCENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->editar($docente, $grupo, $estudiante)->assertOk();

        $this->assertSame(1, Estudiante::query()->where('documento_identidad', '5003748-1A')->count());
        $this->assertDatabaseHas('inscripciones', ['estudiante_id' => $estudiante->id, 'grupo_id' => $otroGrupo->id]);
    }

    public function test_without_changes_nothing_is_logged(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $this->editar($docente, $grupo, $estudiante)->assertOk();
        $this->editar($docente, $grupo, $estudiante)->assertOk();

        $this->assertDatabaseCount('bitacora_operaciones', 1);
    }

    public function test_the_document_of_another_student_is_rejected(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante, $otro] = $this->estudiantesInscritos($grupo, 2);

        $this->editar($docente, $grupo, $estudiante, ['documento' => strtolower((string) $otro->documento_identidad)] + self::DATOS)
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'DOCUMENTO_EN_USO');
    }

    public function test_the_data_is_validated_like_a_row_of_the_list(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $this->editar($docente, $grupo, $estudiante, ['documento' => 'abc', 'nombres' => '', 'apellidos' => 'X!!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documento', 'nombres', 'apellidos']);
    }

    public function test_a_teacher_only_edits_students_of_their_own_groups(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $ajeno = $this->grupo($this->docente());
        [$deOtro] = $this->estudiantesInscritos($ajeno, 1);

        $this->editar($docente, $ajeno, $deOtro)->assertForbidden();
        $this->editar($docente, $grupo, $deOtro)->assertNotFound();
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'estudiante.editar']);
    }

    public function test_the_administration_corrects_any_student_from_the_roll(): void
    {
        $estudiante = $this->estudiante(['documento_identidad' => null]);

        $this->actingAs($this->administrador())
            ->putJson("/api/estudiantes/{$estudiante->id}", self::DATOS)
            ->assertOk();

        $this->assertSame('5003748-1A', $estudiante->refresh()->documento_identidad);
        $this->actingAs($this->administrador())
            ->putJson('/api/estudiantes/999999', self::DATOS)
            ->assertNotFound();
        $this->actingAs($this->cuentaDe($this->docente()))
            ->putJson("/api/estudiantes/{$estudiante->id}", self::DATOS)
            ->assertForbidden();
    }
}
