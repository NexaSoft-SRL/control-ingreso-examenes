<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Examenes\Domain\Models\ExamenGrupo;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Rutas 67 a 69: reportar, listar y atender las incidencias de un examen.
 */
final class IncidenciasTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    private Aula $otra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();

        $this->otra = $this->aula('617');
        $this->sumarAula($this->otra);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return TestResponse<Response>
     */
    private function reportar(User $cuenta, array $cambios = []): TestResponse
    {
        return $this->actingAs($cuenta)->postJson("/api/examenes/{$this->examen->id}/incidencias", array_merge([
            'aula_id' => $this->aula->id,
            'tipo' => 'MATERIAL_NO_PERMITIDO',
            'estudiante' => 'Rocha Peredo Fabiola',
            'detalle' => 'Calculadora programable',
        ], $cambios));
    }

    /**
     * @param  TestResponse<Response>  $respuesta
     */
    private function idDe(TestResponse $respuesta): int
    {
        $id = $respuesta->assertCreated()->json('data.id');
        self::assertIsInt($id);

        return $id;
    }

    public function test_a_teacher_reports_an_incident_in_a_room_of_the_exam(): void
    {
        $respuesta = $this->reportar($this->cuentaDocente)
            ->assertCreated()
            ->assertJsonPath('message', 'Incidencia registrada.')
            ->assertJsonPath('data.tipo', 'MATERIAL_NO_PERMITIDO')
            ->assertJsonPath('data.tipo_texto', 'Material no permitido')
            ->assertJsonPath('data.aula', '691A')
            ->assertJsonPath('data.estudiante', 'Rocha Peredo Fabiola')
            ->assertJsonPath('data.detalle', 'Calculadora programable')
            ->assertJsonPath('data.hora', '08:20')
            ->assertJsonPath('data.por', $this->cuentaDocente->name)
            ->assertJsonPath('data.atendida', false);

        $this->assertDatabaseHas('incidencias', [
            'id' => $respuesta->json('data.id'),
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'estudiante_id' => null,
            'estudiante_texto' => 'Rocha Peredo Fabiola',
            'tipo' => 'MATERIAL_NO_PERMITIDO',
            'origen' => 'PERSONA',
            'reportada_por' => $this->cuentaDocente->id,
            'atendida_en' => null,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'incidencia.registrar',
            'tabla_afectada' => 'incidencias',
            'registro_id' => $respuesta->json('data.id'),
        ]);
    }

    public function test_a_university_code_identifies_the_student(): void
    {
        [$estudiante] = $this->habilitados(1);

        $respuesta = $this->reportar($this->cuentaDocente, ['estudiante' => $estudiante->codigo_universitario])
            ->assertCreated()
            ->assertJsonPath(
                'data.estudiante',
                $estudiante->apellidos.' '.$estudiante->nombres.' · '.$estudiante->codigo_universitario,
            );

        $this->assertDatabaseHas('incidencias', [
            'id' => $respuesta->json('data.id'),
            'estudiante_id' => $estudiante->id,
            'estudiante_texto' => $estudiante->codigo_universitario,
        ]);
    }

    public function test_the_student_is_a_code_of_the_exam_or_a_name_in_letters(): void
    {
        [$ajeno] = $this->estudiantesInscritos($this->grupo($this->docente), 1);

        $this->reportar($this->cuentaDocente, ['estudiante' => $ajeno->codigo_universitario])
            ->assertJsonValidationErrors(['estudiante' => 'No inscrito en el examen.']);
        $this->reportar($this->cuentaDocente, ['estudiante' => '99999999'])
            ->assertJsonValidationErrors(['estudiante' => 'No inscrito en el examen.']);
        $this->reportar($this->cuentaDocente, ['estudiante' => 'Juan 123'])
            ->assertJsonValidationErrors(['estudiante' => 'Un código SIS o solo letras.']);
        $this->reportar($this->cuentaDocente, ['estudiante' => '...'])
            ->assertJsonValidationErrors(['estudiante' => 'Un código SIS o solo letras.']);

        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_each_reportable_type_is_accepted_and_the_student_is_optional(): void
    {
        foreach (['INTENTO_NO_AUTORIZADO', 'SUPLANTACION', 'MATERIAL_NO_PERMITIDO', 'CAMBIO_AULA', 'OTRA'] as $tipo) {
            $this->reportar($this->cuentaDocente, ['tipo' => $tipo, 'estudiante' => null])
                ->assertCreated()
                ->assertJsonPath('data.tipo', $tipo)
                ->assertJsonPath('data.estudiante', null);
        }

        $this->assertDatabaseCount('incidencias', 5);
    }

    public function test_an_expulsion_cannot_be_reported_as_a_plain_incident(): void
    {
        $this->reportar($this->cuentaDocente, ['tipo' => 'EXPULSION'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);

        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_the_detail_needs_at_least_five_characters(): void
    {
        $this->reportar($this->cuentaDocente, ['detalle' => 'no'])
            ->assertStatus(422)
            ->assertJsonPath('errors.detalle.0', 'Mínimo 5 caracteres');

        $this->reportar($this->cuentaDocente, ['detalle' => null, 'aula_id' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['detalle', 'aula_id']);
    }

    public function test_the_room_must_belong_to_the_exam(): void
    {
        $this->reportar($this->cuentaDocente, ['aula_id' => $this->aula('SUELTA')->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.aula_id.0', 'El aula no es de este examen.');
    }

    public function test_incidents_are_reported_only_on_the_day_of_the_exam(): void
    {
        $this->fijarHora('2026-10-13 08:20:00');

        $this->reportar($this->cuentaDocente)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'FUERA_DE_FECHA');

        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_an_assistant_reports_only_in_its_own_room(): void
    {
        $auxiliar = $this->cuentaAuxiliar();
        $this->ponerACargo($this->examen, $this->otra, $auxiliar);

        $this->reportar($auxiliar, ['aula_id' => $this->otra->id])
            ->assertCreated()
            ->assertJsonPath('data.aula', '617')
            ->assertJsonPath('data.por', $auxiliar->name);

        $this->reportar($auxiliar)
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseCount('incidencias', 1);
    }

    public function test_reporting_requires_a_session_and_one_of_the_two_permissions(): void
    {
        $this->postJson("/api/examenes/{$this->examen->id}/incidencias", [])->assertUnauthorized();

        $this->reportar(UserFactory::new()->createOne())
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'punto_control');
    }

    public function test_reporting_on_an_unknown_exam_is_not_found(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson('/api/examenes/999999/incidencias', [
                'aula_id' => $this->aula->id,
                'tipo' => 'OTRA',
                'detalle' => 'Algo ocurrió',
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Examen no encontrado.');
    }

    public function test_the_teacher_lists_the_incidents_of_the_exam_newest_first(): void
    {
        $this->reportar($this->cuentaDocente, ['detalle' => 'La primera']);
        $this->travel(2)->minutes();
        $this->reportar($this->cuentaDocente, ['detalle' => 'La segunda', 'aula_id' => $this->otra->id]);

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.detalle', 'La segunda')
            ->assertJsonPath('data.0.aula', '617')
            ->assertJsonPath('data.0.hora', '08:22')
            ->assertJsonPath('data.1.detalle', 'La primera')
            ->assertJsonPath('meta.sin_atender', 2)
            ->assertJsonPath('meta.todas', 2);
    }

    public function test_the_list_filters_by_room_and_by_pending(): void
    {
        $primera = $this->idDe($this->reportar($this->cuentaDocente, ['detalle' => 'La primera']));
        $this->reportar($this->cuentaDocente, ['detalle' => 'La segunda', 'aula_id' => $this->otra->id]);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$primera}/atencion")
            ->assertOk();

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias?estado=sin_atender")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.detalle', 'La segunda')
            ->assertJsonPath('meta.sin_atender', 1)
            ->assertJsonPath('meta.todas', 2);

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias?aula={$this->aula->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.detalle', 'La primera')
            ->assertJsonPath('data.0.atendida', true)
            ->assertJsonPath('meta.sin_atender', 0)
            ->assertJsonPath('meta.todas', 1);

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias?estado=cualquiera")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    public function test_attempts_recorded_by_the_door_are_not_listed(): void
    {
        [$sinRevisar] = $this->estudiantesInscritos($this->grupo, 1);
        $this->verificar($this->cuentaDocente, $sinRevisar->codigo_universitario)
            ->assertJsonPath('veredicto', 'no_habilitado');

        $this->assertSame(1, DB::table('incidencias')->where('origen', 'SISTEMA')->count());

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.todas', 0)
            ->assertJsonPath('meta.sin_atender', 0);
    }

    public function test_an_assistant_lists_only_its_room_and_must_name_it(): void
    {
        $auxiliar = $this->cuentaAuxiliar();
        $this->ponerACargo($this->examen, $this->otra, $auxiliar);
        $this->reportar($this->cuentaDocente, ['detalle' => 'En la 691A']);
        $this->reportar($this->cuentaDocente, ['detalle' => 'En la 617', 'aula_id' => $this->otra->id]);

        $this->actingAs($auxiliar)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias?aula={$this->otra->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.detalle', 'En la 617');

        $this->actingAs($auxiliar)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias")
            ->assertStatus(422)
            ->assertJsonPath('errors.aula.0', 'Indique el aula.');

        $this->actingAs($auxiliar)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias?aula={$this->aula->id}")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);
    }

    public function test_the_list_requires_session_permission_and_an_existing_exam(): void
    {
        $this->getJson("/api/examenes/{$this->examen->id}/incidencias")->assertUnauthorized();

        $this->actingAs(UserFactory::new()->createOne())
            ->getJson("/api/examenes/{$this->examen->id}/incidencias")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'seguimiento_vivo');

        $this->actingAs($this->cuentaDocente)
            ->getJson('/api/examenes/999999/incidencias')
            ->assertNotFound();
    }

    public function test_a_teacher_of_the_exam_attends_an_incident_once(): void
    {
        $id = $this->idDe($this->reportar($this->cuentaDocente));
        $this->travel(4)->minutes();

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$id}/atencion")
            ->assertOk()
            ->assertJsonPath('message', 'Incidencia atendida.');

        $this->assertDatabaseHas('incidencias', [
            'id' => $id,
            'atendida_en' => '2026-10-12 08:24:00',
            'atendida_por' => $this->cuentaDocente->id,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'incidencia.atender',
            'registro_id' => $id,
        ]);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$id}/atencion")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'YA_ATENDIDA');
    }

    public function test_the_list_tells_who_attended_each_incident_and_when(): void
    {
        $atendida = $this->idDe($this->reportar($this->cuentaDocente, ['detalle' => 'La atendida']));
        $this->travel(4)->minutes();

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$atendida}/atencion")
            ->assertOk();

        $atendidaEn = now()->format('Y-m-d\TH:i:sP');
        $this->travel(1)->minutes();
        $this->reportar($this->cuentaDocente, ['detalle' => 'La pendiente'])
            ->assertJsonPath('data.atendida_por', null)
            ->assertJsonPath('data.atendida_en', null);

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias")
            ->assertOk()
            ->assertJsonPath('data.0.detalle', 'La pendiente')
            ->assertJsonPath('data.0.atendida', false)
            ->assertJsonPath('data.0.atendida_por', null)
            ->assertJsonPath('data.0.atendida_en', null)
            ->assertJsonPath('data.1.detalle', 'La atendida')
            ->assertJsonPath('data.1.atendida', true)
            ->assertJsonPath('data.1.atendida_por', $this->cuentaDocente->name)
            ->assertJsonPath('data.1.atendida_en', $atendidaEn);
    }

    public function test_the_teacher_of_an_included_group_also_attends(): void
    {
        $colega = $this->usuarioConPermisos(['seguimiento_vivo']);
        $grupoDelColega = $this->grupo($this->docenteConCuenta($colega), null, '3');
        ExamenGrupo::create([
            'examen_id' => $this->examen->id,
            'grupo_id' => $grupoDelColega->id,
        ]);
        $id = $this->idDe($this->reportar($this->cuentaDocente));

        $this->actingAs($colega)
            ->postJson("/api/incidencias/{$id}/atencion")
            ->assertOk();

        $this->assertDatabaseHas('incidencias', ['id' => $id, 'atendida_por' => $colega->id]);
    }

    public function test_an_assistant_cannot_attend_incidents(): void
    {
        $auxiliar = $this->cuentaAuxiliar();
        $this->ponerACargo($this->examen, $this->aula, $auxiliar);
        $id = $this->idDe($this->reportar($auxiliar));

        $this->actingAs($auxiliar)
            ->postJson("/api/incidencias/{$id}/atencion")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseHas('incidencias', ['id' => $id, 'atendida_en' => null]);
    }

    public function test_attending_requires_session_permission_and_an_existing_incident(): void
    {
        $id = $this->idDe($this->reportar($this->cuentaDocente));

        $this->app['auth']->forgetGuards();
        $this->postJson("/api/incidencias/{$id}/atencion")->assertUnauthorized();

        $this->actingAs(UserFactory::new()->createOne())
            ->postJson("/api/incidencias/{$id}/atencion")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'seguimiento_vivo');

        $this->actingAs($this->cuentaDocente)
            ->postJson('/api/incidencias/999999/atencion')
            ->assertNotFound()
            ->assertJsonPath('message', 'Incidencia no encontrada.');
    }

    public function test_each_incident_carries_its_severity(): void
    {
        $this->reportar($this->cuentaDocente, ['tipo' => 'SUPLANTACION'])
            ->assertJsonPath('data.gravedad', 'MUY_GRAVE')
            ->assertJsonPath('data.gravedad_texto', 'Muy grave');
        $this->reportar($this->cuentaDocente, ['tipo' => 'DISPOSITIVO_NO_PERMITIDO', 'detalle' => 'Reloj inteligente'])
            ->assertJsonPath('data.tipo_texto', 'Dispositivo no permitido')
            ->assertJsonPath('data.gravedad', 'GRAVE')
            ->assertJsonPath('data.gravedad_texto', 'Grave');
        $this->reportar($this->cuentaDocente, ['tipo' => 'CAMBIO_AULA'])
            ->assertJsonPath('data.gravedad', 'LEVE')
            ->assertJsonPath('data.gravedad_texto', 'Leve');
    }

    public function test_a_repeated_entry_cannot_be_reported_by_hand(): void
    {
        $this->reportar($this->cuentaDocente, ['tipo' => 'INGRESO_REPETIDO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);
    }

    public function test_an_identification_used_again_later_is_listed_as_a_serious_incident(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'admitido');
        $this->travel(10)->minutes();

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'ya_ingreso')
            ->assertJsonPath('pie', 'Incidencia registrada');

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/incidencias")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'INGRESO_REPETIDO')
            ->assertJsonPath('data.0.gravedad', 'GRAVE')
            ->assertJsonPath('data.0.detalle', 'Código o C.I. ya usado. Ingresó a las 08:20 en esta aula.')
            ->assertJsonPath('data.0.atendida', false);
    }

    public function test_reading_the_same_credential_again_right_away_is_not_an_incident(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario);
        $this->travel(30)->seconds();

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'ya_ingreso')
            ->assertJsonPath('pie', null);

        $this->assertSame(0, DB::table('incidencias')->where('tipo', 'INGRESO_REPETIDO')->count());
    }

    public function test_the_teacher_corrects_the_type_and_detail(): void
    {
        $id = $this->idDe($this->reportar($this->cuentaDocente));

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$id}/correccion", [
                'tipo' => 'DISPOSITIVO_NO_PERMITIDO',
                'detalle' => 'Reloj inteligente en la muñeca',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Incidencia corregida.')
            ->assertJsonPath('data.tipo', 'DISPOSITIVO_NO_PERMITIDO')
            ->assertJsonPath('data.detalle', 'Reloj inteligente en la muñeca')
            ->assertJsonPath('data.corregida', true)
            ->assertJsonPath('data.corregida_por', $this->cuentaDocente->name);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'incidencia.corregir',
            'registro_id' => $id,
        ]);
    }

    public function test_a_repeated_entry_keeps_its_type_when_corrected(): void
    {
        $id = $this->idDe($this->reportar($this->cuentaDocente));
        DB::table('incidencias')->where('id', $id)->update(['tipo' => 'INGRESO_REPETIDO']);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$id}/correccion", ['tipo' => 'OTRA', 'detalle' => 'Otra cosa'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);
    }

    public function test_an_assistant_cannot_correct_incidents(): void
    {
        $id = $this->idDe($this->reportar($this->cuentaDocente));

        $this->actingAs($this->cuentaAuxiliar())
            ->postJson("/api/incidencias/{$id}/correccion", ['tipo' => 'OTRA', 'detalle' => 'Otra cosa'])
            ->assertForbidden();
    }

    public function test_a_photo_backs_the_incident_and_only_who_sees_it_can_open_it(): void
    {
        Storage::fake('local');

        $respuesta = $this->actingAs($this->cuentaDocente)->post(
            "/api/examenes/{$this->examen->id}/incidencias",
            [
                'aula_id' => $this->aula->id,
                'tipo' => 'MATERIAL_NO_PERMITIDO',
                'detalle' => 'Hoja con fórmulas',
                'evidencia' => UploadedFile::fake()->image('hoja.png', 3000, 2000),
            ],
            ['Accept' => 'application/json'],
        );
        $id = $this->idDe($respuesta);
        $respuesta->assertJsonPath('data.evidencia', true);

        $respuesta->assertJsonPath('data.evidencias', 1);
        $ruta = DB::table('evidencias_incidencia')->where('incidencia_id', $id)->where('orden', 1)->value('ruta');
        self::assertIsString($ruta);
        Storage::disk('local')->assertExists($ruta);
        $tamano = getimagesizefromstring(Storage::disk('local')->get($ruta) ?? '');
        self::assertIsArray($tamano);
        $this->assertSame([1280, 853], [$tamano[0], $tamano[1]]);

        $this->actingAs($this->cuentaDocente)
            ->get("/api/incidencias/{$id}/evidencia")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($this->docenteAjeno())
            ->getJson("/api/incidencias/{$id}/evidencia")
            ->assertForbidden();
    }

    public function test_the_evidence_must_be_an_image(): void
    {
        $this->actingAs($this->cuentaDocente)->post(
            "/api/examenes/{$this->examen->id}/incidencias",
            [
                'aula_id' => $this->aula->id,
                'tipo' => 'OTRA',
                'detalle' => 'Con un archivo',
                'evidencia' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf'),
            ],
            ['Accept' => 'application/json'],
        )->assertStatus(422)->assertJsonValidationErrors(['evidencia']);
    }

    public function test_up_to_three_photos_back_the_incident_and_each_one_opens_by_its_number(): void
    {
        Storage::fake('local');

        $respuesta = $this->actingAs($this->cuentaDocente)->post(
            "/api/examenes/{$this->examen->id}/incidencias",
            [
                'aula_id' => $this->aula->id,
                'tipo' => 'DISPOSITIVO_NO_PERMITIDO',
                'detalle' => 'Celular bajo la hoja',
                'evidencias' => [
                    UploadedFile::fake()->image('uno.jpg', 800, 600),
                    UploadedFile::fake()->image('dos.jpg', 800, 600),
                    UploadedFile::fake()->image('tres.jpg', 800, 600),
                ],
            ],
            ['Accept' => 'application/json'],
        );
        $id = $this->idDe($respuesta);
        $respuesta->assertJsonPath('data.evidencias', 3);

        $this->assertSame(
            [1, 2, 3],
            DB::table('evidencias_incidencia')->where('incidencia_id', $id)->orderBy('orden')->pluck('orden')->all(),
        );

        foreach ([1, 2, 3] as $numero) {
            $this->actingAs($this->cuentaDocente)
                ->get("/api/incidencias/{$id}/evidencias/{$numero}")
                ->assertOk()
                ->assertHeader('Content-Type', 'image/jpeg');
        }

        $this->actingAs($this->cuentaDocente)->getJson("/api/incidencias/{$id}/evidencias/4")->assertNotFound();
        $this->actingAs($this->docenteAjeno())->getJson("/api/incidencias/{$id}/evidencias/2")->assertForbidden();
    }

    public function test_more_than_three_photos_are_rejected(): void
    {
        $this->actingAs($this->cuentaDocente)->post(
            "/api/examenes/{$this->examen->id}/incidencias",
            [
                'aula_id' => $this->aula->id,
                'tipo' => 'OTRA',
                'detalle' => 'Demasiadas fotos',
                'evidencias' => array_map(static fn (int $i) => UploadedFile::fake()->image("{$i}.jpg"), [1, 2, 3, 4]),
            ],
            ['Accept' => 'application/json'],
        )->assertStatus(422)->assertJsonValidationErrors(['evidencias' => 'Máximo 3 fotos']);

        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_correcting_adds_photos_without_removing_the_previous_ones(): void
    {
        Storage::fake('local');
        $id = $this->idDe($this->reportar($this->cuentaDocente));

        $this->actingAs($this->cuentaDocente)->post(
            "/api/incidencias/{$id}/correccion",
            ['tipo' => 'MATERIAL_NO_PERMITIDO', 'detalle' => 'Calculadora', 'evidencia' => UploadedFile::fake()->image('a.jpg')],
            ['Accept' => 'application/json'],
        )->assertOk();
        $primera = DB::table('evidencias_incidencia')->where('incidencia_id', $id)->value('ruta');
        self::assertIsString($primera);

        $this->actingAs($this->cuentaDocente)->post(
            "/api/incidencias/{$id}/correccion",
            [
                'tipo' => 'MATERIAL_NO_PERMITIDO',
                'detalle' => 'Calculadora',
                'evidencias' => [UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')],
            ],
            ['Accept' => 'application/json'],
        )->assertOk()->assertJsonPath('data.evidencias', 3);

        // Es evidencia: la primera sigue ahi.
        Storage::disk('local')->assertExists($primera);

        // Con tres, no entra otra.
        $this->actingAs($this->cuentaDocente)->post(
            "/api/incidencias/{$id}/correccion",
            ['tipo' => 'MATERIAL_NO_PERMITIDO', 'detalle' => 'Calculadora', 'evidencias' => [UploadedFile::fake()->image('d.jpg')]],
            ['Accept' => 'application/json'],
        )->assertStatus(422)->assertJsonValidationErrors(['evidencias' => 'Ya tiene 3 fotos']);
    }

    public function test_the_teacher_corrects_the_student_by_university_code(): void
    {
        [$estudiante] = $this->habilitados(1);
        $id = $this->idDe($this->reportar($this->cuentaDocente));

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$id}/correccion", [
                'tipo' => 'MATERIAL_NO_PERMITIDO',
                'detalle' => 'Calculadora programable',
                'estudiante' => $estudiante->codigo_universitario,
            ])
            ->assertOk()
            ->assertJsonPath('data.estudiante', $estudiante->apellidos.' '.$estudiante->nombres.' · '.$estudiante->codigo_universitario);

        $this->assertDatabaseHas('incidencias', ['id' => $id, 'estudiante_id' => $estudiante->id]);
    }

    public function test_the_text_written_by_the_door_is_kept_when_correcting(): void
    {
        $id = $this->idDe($this->reportar($this->cuentaDocente));
        DB::table('incidencias')->where('id', $id)->update([
            'tipo' => 'INGRESO_REPETIDO',
            'detalle' => 'Código QR ya usado. Ingresó a las 08:02 en esta aula.',
        ]);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/incidencias/{$id}/correccion", ['tipo' => 'INGRESO_REPETIDO', 'detalle' => 'Otro texto cualquiera'])
            ->assertOk()
            ->assertJsonPath('data.detalle', 'Código QR ya usado. Ingresó a las 08:02 en esta aula.');
    }
}
