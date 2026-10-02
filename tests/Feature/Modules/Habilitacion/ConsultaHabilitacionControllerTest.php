<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Habilitacion;

use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\StudentFactory;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConsultaHabilitacionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_answers_by_university_code_with_the_enabled_condition(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $examen = $this->crearExamen();
        $estudiante = StudentFactory::new()->create([
            'codigo_universitario' => '202600101',
            'ci' => '7001001',
            'nombre' => 'Ana',
            'apellido' => 'Rojas',
            'carrera' => 'Ingeniería de Sistemas',
        ]);
        $this->registrarCondicion($examen, $estudiante->getKey(), 'HABILITADO', null);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=202600101")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo_universitario', '202600101')
            ->assertJsonPath('data.0.ci', '7001001')
            ->assertJsonPath('data.0.nombre', 'Ana')
            ->assertJsonPath('data.0.apellido', 'Rojas')
            ->assertJsonPath('data.0.condicion', 'HABILITADO')
            ->assertJsonPath('data.0.habilitado', true)
            ->assertJsonPath('data.0.motivo', null)
            ->assertJsonPath('data.0.ambiente', null);
    }

    public function test_it_answers_by_identity_document_with_the_reason_of_the_disabled(): void
    {
        $this->travelTo('2026-10-02 14:30:00');
        $personal = $this->usuarioConRol('Personal');
        $docente = UserFactory::new()->createOne(['nombre' => 'Docente Responsable']);
        $examen = $this->crearExamen();
        $estudiante = StudentFactory::new()->create(['ci' => '7002002']);
        $this->registrarCondicion(
            $examen,
            $estudiante->getKey(),
            'NO_HABILITADO',
            'Adeuda la matrícula del semestre',
            $docente->getKey(),
        );

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=7002002")
            ->assertOk()
            ->assertJsonPath('data.0.condicion', 'NO_HABILITADO')
            ->assertJsonPath('data.0.habilitado', false)
            ->assertJsonPath('data.0.motivo', 'Adeuda la matrícula del semestre')
            ->assertJsonPath('data.0.registrado_por', 'Docente Responsable')
            ->assertJsonPath('data.0.fecha_registro', '2026-10-02T14:30:00+00:00');
    }

    public function test_a_student_without_a_registered_condition_is_not_enabled(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $examen = $this->crearExamen();
        StudentFactory::new()->create(['codigo_universitario' => '202600303']);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=202600303")
            ->assertOk()
            ->assertJsonPath('data.0.condicion', 'NO_HABILITADO')
            ->assertJsonPath('data.0.habilitado', false)
            ->assertJsonPath('data.0.motivo', null)
            ->assertJsonPath('data.0.registrado_por', null);
    }

    public function test_the_condition_belongs_to_the_exam_that_is_consulted(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $parcial = $this->crearExamen('Primer parcial');
        $final = $this->crearExamen('Examen final');
        $estudiante = StudentFactory::new()->create(['codigo_universitario' => '202600404']);
        $this->registrarCondicion($parcial, $estudiante->getKey(), 'HABILITADO', null);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$parcial}?identificador=202600404")
            ->assertJsonPath('data.0.habilitado', true);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$final}?identificador=202600404")
            ->assertJsonPath('data.0.habilitado', false);
    }

    public function test_the_same_value_can_match_one_code_and_another_document(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $examen = $this->crearExamen();
        StudentFactory::new()->create(['codigo_universitario' => '8800880', 'apellido' => 'Zenteno']);
        StudentFactory::new()->create(['ci' => '8800880', 'apellido' => 'Arce']);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=8800880")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.apellido', 'Arce')
            ->assertJsonPath('data.1.apellido', 'Zenteno');
    }

    public function test_it_reports_unknown_students_inactive_students_and_unknown_exams(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $examen = $this->crearExamen();
        StudentFactory::new()->create(['codigo_universitario' => '202600505', 'activo' => false]);

        $mensaje = 'Ningún estudiante activo del padrón tiene ese código universitario o documento.';

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=000000000")
            ->assertNotFound()
            ->assertJsonPath('message', $mensaje);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=202600505")
            ->assertNotFound()
            ->assertJsonPath('message', $mensaje);

        $this->actingAs($personal)
            ->getJson('/api/consulta-habilitacion/examenes/999999?identificador=202600505')
            ->assertNotFound()
            ->assertJsonPath('message', 'El examen no existe.');
    }

    public function test_the_identifier_is_required_and_surrounding_spaces_are_ignored(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $examen = $this->crearExamen();
        StudentFactory::new()->create(['codigo_universitario' => '202600606']);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}")
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.identificador.0',
                'Escribe el código universitario o el documento de identidad.',
            );

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=%20202600606%20")
            ->assertOk()
            ->assertJsonPath('data.0.codigo_universitario', '202600606');
    }

    public function test_a_document_with_a_complement_is_found_in_lowercase(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $examen = $this->crearExamen();
        StudentFactory::new()->create(['ci' => '7003003-1A', 'apellido' => 'Complemento']);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=7003003-1a")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ci', '7003003-1A')
            ->assertJsonPath('data.0.apellido', 'Complemento');
    }

    public function test_control_staff_can_consult_but_not_change_the_roster(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $examen = $this->crearExamen();
        $estudiante = StudentFactory::new()->create();

        $this->actingAs($personal)
            ->getJson('/api/consulta-habilitacion/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $examen);

        $this->actingAs($personal)
            ->postJson("/api/habilitacion/examenes/{$examen}/condiciones", [
                'estudiante_ids' => [$estudiante->getKey()],
                'condicion' => 'HABILITADO',
            ])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'habilitacion');
    }

    public function test_a_role_without_the_control_point_permission_is_denied(): void
    {
        $docente = $this->usuarioConRol('Docente');
        $examen = $this->crearExamen();

        $this->actingAs($docente)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=202600101")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'punto_control');
    }

    public function test_the_consultation_requires_a_session(): void
    {
        $examen = $this->crearExamen();

        $this->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=202600101")
            ->assertUnauthorized();

        $this->getJson('/api/consulta-habilitacion/examenes')->assertUnauthorized();
    }

    public function test_the_consultation_does_not_write_to_the_audit_log(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $examen = $this->crearExamen();
        StudentFactory::new()->create(['codigo_universitario' => '202600707']);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=202600707")
            ->assertOk();

        $this->assertDatabaseCount('bitacora_operaciones', 0);
        $this->assertDatabaseCount('habilitaciones_examen', 0);
    }

    public function test_it_answers_in_less_than_three_seconds_with_a_full_roster(): void
    {
        $personal = $this->usuarioConRol('Personal');
        $examen = $this->crearExamen();
        $ahora = now();
        $padron = [];

        // Un padrón del tamaño del que carga la HU-04: dos mil estudiantes.
        for ($i = 1; $i <= 2000; $i++) {
            $padron[] = [
                'codigo_universitario' => sprintf('2026%05d', $i),
                'ci' => sprintf('9%07d', $i),
                'nombre' => 'Estudiante',
                'apellido' => sprintf('Padrón %04d', $i),
                'carrera' => 'Ingeniería de Sistemas',
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($padron, 500) as $lote) {
            DB::table('students')->insert($lote);
        }

        $ids = DB::table('students')->orderBy('id')->pluck('id');
        $condiciones = [];

        foreach ($ids as $posicion => $id) {
            $condiciones[] = [
                'examen_id' => $examen,
                'estudiante_id' => $id,
                'estado' => $posicion % 4 === 0 ? 'NO_HABILITADO' : 'HABILITADO',
                'motivo' => $posicion % 4 === 0 ? 'Adeuda la matrícula del semestre' : null,
                'usuario_id' => $personal->getKey(),
                'fecha_habilitacion' => $ahora,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($condiciones, 500) as $lote) {
            DB::table('habilitaciones_examen')->insert($lote);
        }

        $inicio = microtime(true);

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=202601999")
            ->assertOk()
            ->assertJsonPath('data.0.apellido', 'Padrón 1999');

        $this->actingAs($personal)
            ->getJson("/api/consulta-habilitacion/examenes/{$examen}?identificador=90002000")
            ->assertOk()
            ->assertJsonPath('data.0.apellido', 'Padrón 2000');

        $this->assertLessThan(3.0, microtime(true) - $inicio);
    }

    private function usuarioConRol(string $rol): User
    {
        $this->seed(RolePermissionSeeder::class);

        return UserFactory::new()->createOne([
            'role_id' => DB::table('roles')->where('name', $rol)->value('id'),
        ]);
    }

    private function registrarCondicion(
        int $examen,
        mixed $estudianteId,
        string $estado,
        ?string $motivo,
        mixed $usuarioId = null,
    ): void {
        DB::table('habilitaciones_examen')->insert([
            'examen_id' => $examen,
            'estudiante_id' => $estudianteId,
            'estado' => $estado,
            'motivo' => $motivo,
            'usuario_id' => $usuarioId,
            'fecha_habilitacion' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearExamen(string $nombre = 'Primer parcial'): int
    {
        $docente = Docente::query()->firstOrCreate(
            ['codigo_docente' => 'DOC-CON-001'],
            ['nombres' => 'Docente', 'apellidos' => 'Prueba', 'estado' => true],
        );
        $asignatura = Asignatura::query()->firstOrCreate(
            ['codigo' => 'INF-CON-001'],
            ['nombre' => 'Asignatura consulta', 'semestre' => '8', 'estado' => true],
        );
        $grupo = $asignatura->grupos()->firstOrCreate(
            ['codigo_grupo' => 'A'],
            ['docente_id' => $docente->getKey(), 'cupo' => 30],
        );

        return (int) DB::table('examenes')->insertGetId([
            'grupo_id' => $grupo->getKey(),
            'nombre' => $nombre,
            'fecha' => '2026-10-15',
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
        ]);
    }
}
