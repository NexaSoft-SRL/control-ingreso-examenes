<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Habilitacion;

use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\StudentFactory;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class HabilitacionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_exam_roster_defaults_to_not_enabled_and_lists_only_active_students(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $activo = StudentFactory::new()->create(['nombre' => 'Estudiante', 'apellido' => 'Activo']);
        StudentFactory::new()->create(['activo' => false]);

        $this->actingAs($usuario)
            ->getJson("/api/habilitacion/examenes/{$examen}/estudiantes")
            ->assertOk()
            ->assertJsonPath('totales.total', 1)
            ->assertJsonPath('totales.habilitados', 0)
            ->assertJsonPath('totales.no_habilitados', 1)
            ->assertJsonPath('data.0.id', $activo->getKey())
            ->assertJsonPath('data.0.condicion', 'NO_HABILITADO')
            ->assertJsonPath('data.0.registrado_por', null);
    }

    public function test_it_registers_and_replaces_a_condition_in_one_batch_with_audit_author(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $primero = StudentFactory::new()->create();
        $segundo = StudentFactory::new()->create();

        $url = "/api/habilitacion/examenes/{$examen}/condiciones";
        $payload = [
            'estudiante_ids' => [$primero->getKey(), $segundo->getKey()],
            'condicion' => 'HABILITADO',
        ];

        $this->actingAs($usuario)->postJson($url, $payload)->assertOk();

        $this->assertDatabaseCount('habilitaciones_examen', 2);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $usuario->getKey(),
            'operacion' => 'habilitacion.estudiante.habilitar',
        ]);

        $this->actingAs($usuario)->postJson($url, [
            ...$payload,
            'condicion' => 'NO_HABILITADO',
            'motivo' => 'Documentación pendiente',
        ])->assertOk();

        $this->assertDatabaseCount('habilitaciones_examen', 2);
        $this->assertDatabaseHas('habilitaciones_examen', [
            'examen_id' => $examen,
            'estudiante_id' => $primero->getKey(),
            'estado' => 'NO_HABILITADO',
            'motivo' => 'Documentación pendiente',
            'usuario_id' => $usuario->getKey(),
        ]);
        $this->assertSame(4, DB::table('bitacora_operaciones')->count());

        $this->actingAs($usuario)
            ->getJson("/api/habilitacion/examenes/{$examen}/estudiantes")
            ->assertJsonPath('data.0.condicion', 'NO_HABILITADO')
            ->assertJsonPath('data.0.registrado_por', $usuario->nombre);
    }

    public function test_the_reason_is_mandatory_when_disabling_a_student(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $estudiante = StudentFactory::new()->create();
        $url = "/api/habilitacion/examenes/{$examen}/condiciones";

        $this->actingAs($usuario)
            ->postJson($url, [
                'estudiante_ids' => [$estudiante->getKey()],
                'condicion' => 'NO_HABILITADO',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.motivo.0', 'El motivo es obligatorio para inhabilitar.');

        $this->actingAs($usuario)
            ->postJson($url, [
                'estudiante_ids' => [$estudiante->getKey()],
                'condicion' => 'NO_HABILITADO',
                'motivo' => '     ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo');

        $this->actingAs($usuario)
            ->postJson($url, [
                'estudiante_ids' => [$estudiante->getKey()],
                'condicion' => 'NO_HABILITADO',
                'motivo' => 'no',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.motivo.0',
                'El motivo debe explicar la inhabilitación con al menos 5 caracteres.',
            );

        $this->assertDatabaseCount('habilitaciones_examen', 0);
        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_the_reason_is_cleared_when_the_student_is_enabled_again(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $estudiante = StudentFactory::new()->create();
        $url = "/api/habilitacion/examenes/{$examen}/condiciones";

        $this->actingAs($usuario)->postJson($url, [
            'estudiante_ids' => [$estudiante->getKey()],
            'condicion' => 'NO_HABILITADO',
            'motivo' => 'Adeuda la matrícula del semestre',
        ])->assertOk();

        // Aunque el cliente reenvíe el motivo anterior, al habilitar no se guarda.
        $this->actingAs($usuario)->postJson($url, [
            'estudiante_ids' => [$estudiante->getKey()],
            'condicion' => 'HABILITADO',
            'motivo' => 'Adeuda la matrícula del semestre',
        ])->assertOk()->assertJsonPath('data.0.motivo', null);

        $this->assertDatabaseHas('habilitaciones_examen', [
            'examen_id' => $examen,
            'estudiante_id' => $estudiante->getKey(),
            'estado' => 'HABILITADO',
            'motivo' => null,
        ]);
    }

    public function test_it_keeps_a_record_of_who_registered_the_reason_and_when(): void
    {
        $this->travelTo('2026-10-02 14:30:00');
        $docente = UserFactory::new()->createOne(['nombre' => 'Docente Constancia']);
        $examen = $this->crearExamen();
        $estudiante = StudentFactory::new()->create(['codigo_universitario' => '202600777']);

        $this->actingAs($docente)
            ->postJson("/api/habilitacion/examenes/{$examen}/condiciones", [
                'estudiante_ids' => [$estudiante->getKey()],
                'condicion' => 'NO_HABILITADO',
                'motivo' => 'No presentó el proyecto final',
            ])
            ->assertOk()
            ->assertJsonPath('data.0.motivo', 'No presentó el proyecto final')
            ->assertJsonPath('data.0.registrado_por', 'Docente Constancia')
            ->assertJsonPath('data.0.fecha_habilitacion', '2026-10-02T14:30:00+00:00');

        $descripcion = DB::table('bitacora_operaciones')
            ->where('usuario_id', $docente->getKey())
            ->where('operacion', 'habilitacion.estudiante.inhabilitar')
            ->value('descripcion');

        $this->assertIsString($descripcion);
        $this->assertStringContainsString('Condición NO_HABILITADO registrada', $descripcion);
        $this->assertStringEndsWith('Motivo: No presentó el proyecto final', $descripcion);

        $contenido = $this->actingAs($docente)
            ->get("/api/habilitacion/examenes/{$examen}/exportar")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Registrado por","Fecha de registro"', $contenido);
        $this->assertStringContainsString(
            '"No presentó el proyecto final","Docente Constancia","02/10/2026 10:30"',
            $contenido,
        );
    }

    public function test_the_record_follows_the_last_person_who_changed_the_condition(): void
    {
        $primero = UserFactory::new()->createOne(['nombre' => 'Primer Docente']);
        $segundo = UserFactory::new()->createOne(['nombre' => 'Segundo Docente']);
        $examen = $this->crearExamen();
        $estudiante = StudentFactory::new()->create();
        $url = "/api/habilitacion/examenes/{$examen}/condiciones";

        $this->travelTo('2026-10-02 08:00:00');
        $this->actingAs($primero)->postJson($url, [
            'estudiante_ids' => [$estudiante->getKey()],
            'condicion' => 'NO_HABILITADO',
            'motivo' => 'Falta el pago de la matrícula',
        ])->assertOk();

        $this->travelTo('2026-10-03 09:15:00');
        $this->actingAs($segundo)->postJson($url, [
            'estudiante_ids' => [$estudiante->getKey()],
            'condicion' => 'NO_HABILITADO',
            'motivo' => 'Sigue sin regularizar la matrícula',
        ])
            ->assertOk()
            ->assertJsonPath('data.0.motivo', 'Sigue sin regularizar la matrícula')
            ->assertJsonPath('data.0.registrado_por', 'Segundo Docente')
            ->assertJsonPath('data.0.fecha_habilitacion', '2026-10-03T09:15:00+00:00');
    }

    public function test_it_rejects_missing_students_and_reports_unknown_exams(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();

        $this->actingAs($usuario)
            ->postJson("/api/habilitacion/examenes/{$examen}/condiciones", [
                'estudiante_ids' => [999999],
                'condicion' => 'HABILITADO',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estudiante_ids.0');

        $this->actingAs($usuario)
            ->getJson('/api/habilitacion/examenes/999999/estudiantes')
            ->assertNotFound()
            ->assertJsonPath('message', 'El examen no existe.');
    }

    public function test_a_role_without_the_permission_gets_an_explicit_denial(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $rolPersonal = DB::table('roles')->where('name', 'Personal')->value('id');
        $usuario = UserFactory::new()->createOne(['role_id' => $rolPersonal]);

        $this->actingAs($usuario)
            ->getJson('/api/habilitacion/examenes')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'habilitacion');
    }

    public function test_it_exports_the_required_student_fields_as_csv(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        StudentFactory::new()->create([
            'codigo_universitario' => '202600001',
            'ci' => '1234567',
            'nombre' => 'Ana',
            'apellido' => 'Rojas',
            'carrera' => 'Ingeniería de Sistemas',
        ]);

        $respuesta = $this->actingAs($usuario)
            ->get("/api/habilitacion/examenes/{$examen}/exportar")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('Código,Documento,Nombre,Carrera,Condición,Motivo', $contenido);
        $this->assertStringContainsString('202600001,1234567,"Ana Rojas","Ingeniería de Sistemas",NO_HABILITADO', $contenido);
    }

    private function crearExamen(): int
    {
        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-HAB-001',
            'nombres' => 'Docente',
            'apellidos' => 'Prueba',
            'estado' => true,
        ]);
        $asignatura = Asignatura::query()->create([
            'codigo' => 'INF-HAB-001',
            'nombre' => 'Asignatura habilitación',
            'semestre' => '8',
            'estado' => true,
        ]);
        $grupo = $asignatura->grupos()->create([
            'docente_id' => $docente->getKey(),
            'codigo_grupo' => 'A',
            'cupo' => 30,
        ]);

        return (int) DB::table('examenes')->insertGetId([
            'grupo_id' => $grupo->getKey(),
            'nombre' => 'Primer parcial',
            'fecha' => '2026-10-15',
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
        ]);
    }
}
