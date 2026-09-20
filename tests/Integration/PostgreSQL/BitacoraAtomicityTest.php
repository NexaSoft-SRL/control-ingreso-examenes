<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BitacoraAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_registration_rolls_back_when_audit_insert_fails(): void
    {
        $user = UserFactory::new()->createOne();

        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-BIT-ROLLBACK-001',
            'nombres' => 'Ana',
            'apellidos' => 'Rojas',
            'estado' => true,
        ]);

        DB::statement(
            "ALTER TABLE bitacora_operaciones
             ADD CONSTRAINT hu07_force_register_audit_failure
             CHECK (operacion <> 'asignatura.registrar')"
        );

        $this->withoutExceptionHandling();

        try {
            try {
                $this
                    ->actingAs($user)
                    ->postJson('/api/asignaturas', [
                        'codigo' => 'INF-BIT-ROLLBACK-001',
                        'nombre' => 'Asignatura rollback auditoría',
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

                $this->fail(
                    'La escritura de bitácora debía provocar una excepción PostgreSQL.'
                );
            } catch (QueryException $exception) {
                self::assertStringContainsString(
                    'hu07_force_register_audit_failure',
                    $exception->getMessage()
                );
            }

            $this->assertDatabaseMissing('asignaturas', [
                'codigo' => 'INF-BIT-ROLLBACK-001',
            ]);

            $this->assertDatabaseCount(
                'grupos_asignatura',
                0
            );

            $this->assertDatabaseCount(
                'bitacora_operaciones',
                0
            );
        } finally {
            DB::statement(
                'ALTER TABLE bitacora_operaciones
                 DROP CONSTRAINT IF EXISTS hu07_force_register_audit_failure'
            );
        }
    }

    public function test_subject_deletion_rolls_back_when_audit_insert_fails(): void
    {
        $user = UserFactory::new()->createOne();

        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-BIT-ROLLBACK-002',
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
            'codigo' => 'INF-BIT-ROLLBACK-002',
            'nombre' => 'Asignatura protegida por auditoría',
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

        DB::statement(
            "ALTER TABLE bitacora_operaciones
             ADD CONSTRAINT hu07_force_delete_audit_failure
             CHECK (operacion <> 'asignatura.eliminar')"
        );

        $this->withoutExceptionHandling();

        try {
            try {
                $this
                    ->actingAs($user)
                    ->deleteJson(
                        "/api/asignaturas/{$asignaturaId}"
                    );

                $this->fail(
                    'La escritura de bitácora debía provocar una excepción PostgreSQL.'
                );
            } catch (QueryException $exception) {
                self::assertStringContainsString(
                    'hu07_force_delete_audit_failure',
                    $exception->getMessage()
                );
            }

            $this->assertDatabaseHas('asignaturas', [
                'id' => $asignaturaId,
                'codigo' => 'INF-BIT-ROLLBACK-002',
            ]);

            $this->assertDatabaseHas('grupos_asignatura', [
                'asignatura_id' => $asignaturaId,
                'codigo_grupo' => '1',
            ]);

            $this->assertDatabaseCount(
                'bitacora_operaciones',
                0
            );
        } finally {
            DB::statement(
                'ALTER TABLE bitacora_operaciones
                 DROP CONSTRAINT IF EXISTS hu07_force_delete_audit_failure'
            );
        }
    }

    public function test_audit_user_foreign_key_failure_is_not_translated_as_subject_dependency_and_rolls_back(): void
    {
        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-BIT-FK-001',
            'nombres' => 'María',
            'apellidos' => 'Mendoza',
            'estado' => true,
        ]);

        $docenteId = $docente->getKey();

        if (! is_int($docenteId)) {
            $this->fail(
                'El identificador PostgreSQL del docente debía ser entero.'
            );
        }

        $asignatura = Asignatura::query()->create([
            'codigo' => 'INF-BIT-FK-001',
            'nombre' => 'Asignatura FK auditoría',
            'semestre' => '8',
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
            'cupo' => 30,
        ]);

        /** @var AsignaturaGateway $gateway */
        $gateway = $this->app->make(
            AsignaturaGateway::class
        );

        try {
            $gateway->eliminar(
                $asignaturaId,
                999999999,
            );

            $this->fail(
                'PostgreSQL debía rechazar el usuario inexistente de la bitácora.'
            );
        } catch (QueryException $exception) {
            self::assertStringContainsString(
                'bitacora_operaciones',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('asignaturas', [
            'id' => $asignaturaId,
            'codigo' => 'INF-BIT-FK-001',
        ]);

        $this->assertDatabaseHas('grupos_asignatura', [
            'asignatura_id' => $asignaturaId,
            'codigo_grupo' => '1',
        ]);

        $this->assertDatabaseCount(
            'bitacora_operaciones',
            0
        );
    }
}
