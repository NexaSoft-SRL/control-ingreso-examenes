<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BitacoraRegistroAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_subject_records_audit_operation(): void
    {
        $user = UserFactory::new()->createOne();

        $userId = $user->getKey();

        if (! is_int($userId)) {
            $this->fail(
                'El identificador PostgreSQL del usuario debía ser entero.'
            );
        }

        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-BIT-001',
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'estado' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/asignaturas', [
                'codigo' => 'INF-BIT-001',
                'nombre' => 'Auditoría de Sistemas',
                'semestre' => '8',
                'descripcion' => null,
                'grupos' => [
                    [
                        'codigo_grupo' => '1',
                        'docente_id' => $docente->getKey(),
                        'cupo' => 30,
                    ],
                ],
            ]);

        $response->assertCreated();

        $asignaturaId = $response->json('data.id');

        self::assertIsInt($asignaturaId);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $userId,
            'operacion' => 'asignatura.registrar',
            'tabla_afectada' => 'asignaturas',
            'registro_id' => $asignaturaId,
            'descripcion' => null,
        ]);

        $fechaOperacion = DB::table('bitacora_operaciones')
            ->where('operacion', 'asignatura.registrar')
            ->where('registro_id', $asignaturaId)
            ->value('fecha_operacion');

        self::assertNotNull($fechaOperacion);
    }

    public function test_deleting_subject_records_audit_operation(): void
    {
        $user = UserFactory::new()->createOne();

        $userId = $user->getKey();

        if (! is_int($userId)) {
            $this->fail(
                'El identificador PostgreSQL del usuario debía ser entero.'
            );
        }

        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-BIT-002',
            'nombres' => 'Luis',
            'apellidos' => 'Flores',
            'estado' => true,
        ]);

        $docenteId = $docente->getKey();

        if (! is_int($docenteId)) {
            $this->fail(
                'El identificador PostgreSQL del docente debía ser entero.'
            );
        }

        $asignatura = Asignatura::query()->create([
            'codigo' => 'INF-BIT-002',
            'nombre' => 'Asignatura auditable',
            'semestre' => '7',
            'descripcion' => null,
            'estado' => true,
        ]);

        $asignaturaId = $asignatura->getKey();

        if (! is_int($asignaturaId)) {
            $this->fail(
                'El identificador PostgreSQL de la asignatura debía ser entero.'
            );
        }

        $asignatura->grupos()->create([
            'docente_id' => $docenteId,
            'codigo_grupo' => '1',
            'cupo' => 25,
        ]);

        $this
            ->actingAs($user)
            ->deleteJson("/api/asignaturas/{$asignaturaId}")
            ->assertNoContent();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $userId,
            'operacion' => 'asignatura.eliminar',
            'tabla_afectada' => 'asignaturas',
            'registro_id' => $asignaturaId,
            'descripcion' => null,
        ]);
    }

    public function test_failed_subject_registration_does_not_record_audit_operation(): void
    {
        $user = UserFactory::new()->createOne();

        $this
            ->actingAs($user)
            ->postJson('/api/asignaturas', [
                'codigo' => 'INF-BIT-003',
                'nombre' => 'Registro inválido',
                'semestre' => '8',
                'descripcion' => null,
                'grupos' => [],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }
}
