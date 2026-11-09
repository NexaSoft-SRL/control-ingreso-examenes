<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Estudiantes\Domain\Enums\EstadoConflicto;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Enums\TipoConflicto;
use App\Modules\Estudiantes\Domain\Models\ConflictoPadron;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El docente agrega a un estudiante suelto a la lista de su grupo, con las
 * mismas reglas que una fila del archivo de carga.
 */
final class AgregarInscritoTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const KEVIN = [
        'codigo' => '202104821',
        'documento' => '7928194',
        'nombres' => 'Kevin René',
        'apellidos' => 'Alvarado Claros',
    ];

    // --- Las salidas ---

    public function test_a_student_that_is_not_in_the_roll_is_created_with_teacher_origin_and_enrolled(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->agregar($docente, $grupo, self::KEVIN)
            ->assertCreated()
            ->assertExactJson([
                'message' => 'Estudiante agregado al padrón e inscrito.',
                'resultado' => 'NUEVO',
            ]);

        $estudiante = Estudiante::query()->where('codigo_universitario', '202104821')->firstOrFail();

        $this->assertSame(OrigenEstudiante::Docente, $estudiante->origen);
        $this->assertFalse($estudiante->verificado);
        $this->assertSame('7928194', $estudiante->documento_identidad);
        $this->assertSame($grupo->facultad_id, $estudiante->facultad_id);

        $inscripcion = Inscripcion::query()->where('grupo_id', $grupo->id)->sole();
        $this->assertSame($estudiante->id, $inscripcion->estudiante_id);
        $this->assertSame(OrigenEstudiante::Docente, $inscripcion->via);
        $this->assertSame($docente->user_id, $inscripcion->cargada_por);
        $this->assertNull($inscripcion->baja_en);
    }

    public function test_a_student_already_in_the_roll_is_only_enrolled(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $estudiante = $this->estudiante();

        // Mayusculas, tildes y espacios no son una diferencia.
        $this->agregar($docente, $grupo, [
            'codigo' => '202104821',
            'documento' => '7928194',
            'nombres' => 'KEVIN  RENE',
            'apellidos' => 'alvarado claros',
        ])
            ->assertCreated()
            ->assertJsonPath('resultado', 'INSCRITO')
            ->assertJsonPath('message', 'Estudiante inscrito.');

        $this->assertSame(1, Estudiante::query()->count());
        $this->assertDatabaseHas('inscripciones', [
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $grupo->id,
            'via' => OrigenEstudiante::Docente->value,
        ]);

        // El padron no cambia.
        $estudiante->refresh();
        $this->assertSame('Kevin René', $estudiante->nombres);
        $this->assertTrue($estudiante->verificado);
        $this->assertDatabaseCount('conflictos_padron', 0);
    }

    public function test_a_student_whose_data_differ_from_the_roll_leaves_a_conflict_and_is_not_modified(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $estudiante = $this->estudiante();

        $this->agregar($docente, $grupo, [...self::KEVIN, 'documento' => '9999999'])
            ->assertAccepted()
            ->assertJsonPath('resultado', 'CONFLICTO')
            ->assertJsonPath('message', 'Datos distintos a los del padrón: la inscripción queda pendiente de la administración.');

        $estudiante->refresh();
        $this->assertSame('7928194', $estudiante->documento_identidad);

        // Como en la carga: la inscripcion espera a que se resuelva.
        $this->assertDatabaseMissing('inscripciones', ['grupo_id' => $grupo->id]);
        $this->assertDatabaseHas('conflictos_padron', [
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $grupo->id,
            'carga_id' => null,
            'tipo' => TipoConflicto::DocumentoDistinto->value,
            'documento_nuevo' => '9999999',
            'via' => OrigenEstudiante::Docente->value,
            'estado' => EstadoConflicto::Pendiente->value,
            'reportado_por' => $docente->user_id,
        ]);
    }

    public function test_a_name_difference_is_a_name_conflict_and_repeating_it_does_not_open_another(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $this->estudiante();

        $distinto = [...self::KEVIN, 'nombres' => 'Kevin Rodrigo'];

        $this->agregar($docente, $grupo, $distinto)->assertAccepted();
        $this->agregar($docente, $grupo, $distinto)->assertAccepted();

        $this->assertDatabaseCount('conflictos_padron', 1);
        $this->assertDatabaseHas('conflictos_padron', ['tipo' => TipoConflicto::NombreDistinto->value]);
    }

    public function test_resolving_the_conflict_enrolls_the_student(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $estudiante = $this->estudiante();

        $this->agregar($docente, $grupo, [...self::KEVIN, 'nombres' => 'Kevin Rodrigo'])->assertAccepted();

        $conflicto = ConflictoPadron::query()->sole()->id;

        $this->actingAs($this->administrador())
            ->postJson("/api/estudiantes/conflictos/{$conflicto}/resolucion", ['resolucion' => 'MANTENER_PADRON'])
            ->assertOk();

        $this->assertDatabaseHas('inscripciones', ['estudiante_id' => $estudiante->id, 'grupo_id' => $grupo->id]);
    }

    public function test_a_student_already_enrolled_in_the_group_is_rejected(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->agregar($docente, $grupo, self::KEVIN)->assertCreated();

        $this->agregar($docente, $grupo, self::KEVIN)
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'YA_INSCRITO')
            ->assertJsonValidationErrors(['codigo' => 'El estudiante ya está inscrito en este grupo.']);

        $this->assertSame(1, Inscripcion::query()->count());
    }

    public function test_a_new_student_with_the_document_of_another_one_is_rejected(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $this->estudiante();

        $this->agregar($docente, $grupo, [...self::KEVIN, 'codigo' => '201901349'])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'DOCUMENTO_EN_USO')
            ->assertJsonValidationErrors(['documento' => 'Ese documento ya pertenece a otro estudiante.']);

        $this->assertSame(1, Estudiante::query()->count());
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_adding_a_student_who_was_removed_enrolls_him_again(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->agregar($docente, $grupo, self::KEVIN)->assertCreated();
        $estudiante = Estudiante::query()->firstOrFail();

        $this->actingAs($this->cuentaDe($docente))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos/{$estudiante->id}/baja", ['motivo' => 'CARGADO_POR_ERROR'])
            ->assertOk();

        $this->agregar($docente, $grupo, self::KEVIN)
            ->assertCreated()
            ->assertJsonPath('resultado', 'REINSCRITO');

        $inscripcion = Inscripcion::query()->sole();
        $this->assertNull($inscripcion->baja_en);
        $this->assertNull($inscripcion->baja_por);
        $this->assertNull($inscripcion->motivo_baja);

        $this->listar($docente, $grupo)->assertJsonPath('meta.total', 1);
    }

    // --- Validacion, como una fila del archivo ---

    public function test_every_field_is_required(): void
    {
        $docente = $this->docente();

        $this->agregar($docente, $this->grupo($docente), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'codigo' => 'El código SIS es obligatorio.',
                'documento' => 'El documento es obligatorio.',
                'nombres' => 'Los nombres son obligatorios.',
                'apellidos' => 'Los apellidos son obligatorios.',
            ]);

        $this->assertDatabaseCount('estudiantes', 0);
    }

    public function test_the_code_must_have_between_5_and_10_digits(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        foreach (['1234', '12345678901', '20210A821', ' '] as $codigo) {
            $this->agregar($docente, $grupo, [...self::KEVIN, 'codigo' => $codigo])
                ->assertUnprocessable()
                ->assertJsonValidationErrorFor('codigo');
        }

        $this->agregar($docente, $grupo, [...self::KEVIN, 'codigo' => '12345678901'])
            ->assertJsonValidationErrors(['codigo' => 'El código SIS debe tener entre 5 y 10 dígitos.']);

        $this->agregar($docente, $grupo, [...self::KEVIN, 'codigo' => '12345'])->assertCreated();
    }

    public function test_the_document_and_the_names_have_the_limits_of_the_file(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->agregar($docente, $grupo, [
            ...self::KEVIN,
            'documento' => str_repeat('9', 31),
            'nombres' => str_repeat('a', 101),
            'apellidos' => str_repeat('b', 101),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'documento' => 'El documento no puede superar los 30 caracteres.',
                'nombres' => 'Los nombres no pueden superar los 100 caracteres.',
                'apellidos' => 'Los apellidos no pueden superar los 100 caracteres.',
            ]);
    }

    public function test_names_are_words_of_letters_and_the_document_has_its_separator(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        foreach (['A', 'Ana--María', "O'", 'Juan..'] as $nombre) {
            $this->agregar($docente, $grupo, [...self::KEVIN, 'nombres' => $nombre])
                ->assertJsonValidationErrors(['nombres' => 'Solo letras, sin signos seguidos.']);
        }
        foreach (['123', '12345678901', '5003748LP', '5003748--1A'] as $documento) {
            $this->agregar($docente, $grupo, [...self::KEVIN, 'documento' => $documento])
                ->assertJsonValidationErrorFor('documento');
        }

        $this->agregar($docente, $grupo, [
            ...self::KEVIN,
            'nombres' => 'Ma. José',
            'apellidos' => "D'Alessandro Pérez-Gómez",
            'documento' => '5003748-1A',
        ])->assertCreated();
    }

    // --- Acceso, alcance, periodo y bitacora ---

    public function test_a_guest_cannot_add(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->postJson("/api/docente/grupos/{$grupo->id}/inscritos", self::KEVIN)->assertUnauthorized();
    }

    public function test_a_role_without_the_permission_cannot_add(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->actingAs($this->administrador())
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos", self::KEVIN)
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'mis_grupos');
    }

    public function test_a_teacher_cannot_add_to_a_group_of_another_teacher(): void
    {
        $docente = $this->docente();
        $ajeno = $this->grupo($this->docente());

        $this->agregar($docente, $ajeno, self::KEVIN)
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este grupo no está a su cargo.', 'alcance' => true]);

        $this->assertDatabaseCount('estudiantes', 0);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_a_group_that_does_not_exist_is_not_found(): void
    {
        $docente = $this->docente();

        $this->actingAs($this->cuentaDe($docente))
            ->postJson('/api/docente/grupos/999999/inscritos', self::KEVIN)
            ->assertNotFound()
            ->assertJsonPath('message', 'Grupo no encontrado.');
    }

    public function test_a_group_of_a_closed_period_does_not_accept_students(): void
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

        $this->agregar($docente, $this->grupo($docente, null, null, $cerrado), self::KEVIN)
            ->assertConflict()
            ->assertJsonPath('codigo', 'PERIODO_CERRADO');

        $this->assertDatabaseCount('estudiantes', 0);
    }

    public function test_adding_is_recorded_in_the_audit_log(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, null, '7');

        $this->agregar($docente, $grupo, self::KEVIN)->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'inscritos.agregar',
            'usuario_id' => $docente->user_id,
            'tabla_afectada' => 'inscripciones',
            'registro_id' => Inscripcion::query()->value('id'),
        ]);

        $descripcion = DB::table('bitacora_operaciones')->where('operacion', 'inscritos.agregar')->value('descripcion');

        $this->assertIsString($descripcion);
        $this->assertStringContainsString('202104821', $descripcion);
        $this->assertStringContainsString('grupo 7', $descripcion);
        $this->assertStringContainsString('nuevo en el padrón', $descripcion);
    }

    public function test_a_conflict_is_also_recorded_in_the_audit_log(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $this->estudiante();

        $this->agregar($docente, $grupo, [...self::KEVIN, 'documento' => '9999999'])->assertAccepted();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'inscritos.agregar',
            'tabla_afectada' => 'conflictos_padron',
            'registro_id' => DB::table('conflictos_padron')->value('id'),
        ]);
    }

    /**
     * @param  array<string, string>  $datos
     * @return TestResponse<JsonResponse>
     */
    private function agregar(Docente $docente, Grupo $grupo, array $datos): TestResponse
    {
        return $this->actingAs($this->cuentaDe($docente))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos", $datos);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function listar(Docente $docente, Grupo $grupo): TestResponse
    {
        return $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/docente/grupos/{$grupo->id}/inscritos")
            ->assertOk();
    }
}
