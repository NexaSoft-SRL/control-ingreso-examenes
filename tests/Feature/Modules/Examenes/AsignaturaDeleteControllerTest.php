<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\UserFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AsignaturaDeleteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_subject_without_exam_dependencies(): void
    {
        $user = UserFactory::new()->createOne();

        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-DELETE-001',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'estado' => true,
        ]);

        $docenteId = $docente->getKey();

        if (! is_int($docenteId)) {
            $this->fail(
                'El identificador PostgreSQL del docente debía ser entero.'
            );
        }

        $asignatura = Asignatura::query()->create([
            'codigo' => 'INF-DELETE-001',
            'nombre' => 'Asignatura eliminable',
            'semestre' => '6',
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

        $asignatura->grupos()->create([
            'docente_id' => $docenteId,
            'codigo_grupo' => '2',
            'cupo' => 35,
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/asignaturas/{$asignaturaId}");

        $response->dump();

        $response->assertNoContent();

        $this->assertDatabaseMissing('asignaturas', [
            'id' => $asignaturaId,
        ]);

        $this->assertDatabaseMissing('grupos_asignatura', [
            'asignatura_id' => $asignaturaId,
        ]);

        $this->assertDatabaseHas('docentes', [
            'id' => $docenteId,
            'codigo_docente' => 'DOC-DELETE-001',
        ]);
    }

    public function test_guest_cannot_delete_subject(): void
    {
        $asignatura = Asignatura::query()->create([
            'codigo' => 'INF-DELETE-002',
            'nombre' => 'Asignatura protegida por autenticación',
            'semestre' => '6',
            'descripcion' => null,
            'estado' => true,
        ]);

        $asignaturaId = $asignatura->getKey();

        if (! is_int($asignaturaId)) {
            $this->fail(
                'El identificador PostgreSQL de la asignatura debía ser entero.'
            );
        }

        $this
            ->deleteJson("/api/asignaturas/{$asignaturaId}")
            ->assertUnauthorized();

        $this->assertDatabaseHas('asignaturas', [
            'id' => $asignaturaId,
            'codigo' => 'INF-DELETE-002',
        ]);
    }

    public function test_deleting_nonexistent_subject_returns_not_found(): void
    {
        $user = UserFactory::new()->createOne();

        $this
            ->actingAs($user)
            ->deleteJson('/api/asignaturas/999999999')
            ->assertNotFound();
    }

    public function test_subject_with_exam_dependency_cannot_be_deleted_and_transaction_is_rolled_back(): void
    {
        $user = UserFactory::new()->createOne();

        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-DELETE-003',
            'nombres' => 'Luis',
            'apellidos' => 'Rojas',
            'estado' => true,
        ]);

        $docenteId = $docente->getKey();

        if (! is_int($docenteId)) {
            $this->fail(
                'El identificador PostgreSQL del docente debía ser entero.'
            );
        }

        $asignatura = Asignatura::query()->create([
            'codigo' => 'INF-DELETE-003',
            'nombre' => 'Asignatura con examen',
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
            'cupo' => 40,
        ]);

        /*
         * HU-08 todavía no implementa la tabla examenes.
         *
         * Esta tabla existe únicamente durante esta prueba y representa
         * la dependencia referencial ya definida por el modelo:
         *
         * Examen -> Asignatura
         *
         * No forma parte del esquema productivo.
         */
        Schema::dropIfExists('hu05_examenes_guard_test');

        Schema::create(
            'hu05_examenes_guard_test',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('asignatura_id')
                    ->constrained('asignaturas')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();

                $table->string('referencia', 50);
            }
        );

        try {
            DB::table('hu05_examenes_guard_test')->insert([
                'asignatura_id' => $asignaturaId,
                'referencia' => 'EXAMEN-HU05-001',
            ]);

            $response = $this
                ->actingAs($user)
                ->deleteJson("/api/asignaturas/{$asignaturaId}");

            $response
                ->assertConflict()
                ->assertJsonPath(
                    'message',
                    'No se puede eliminar la asignatura porque tiene registros asociados.'
                );

            /*
             * La asignatura debe sobrevivir.
             */
            $this->assertDatabaseHas('asignaturas', [
                'id' => $asignaturaId,
                'codigo' => 'INF-DELETE-003',
            ]);

            /*
             * El grupo también debe sobrevivir.
             *
             * Esto demuestra que el borrado de grupos y asignatura
             * está protegido por una única transacción.
             */
            $this->assertDatabaseHas('grupos_asignatura', [
                'asignatura_id' => $asignaturaId,
                'codigo_grupo' => '1',
            ]);

            /*
             * La dependencia que representa al examen tampoco puede
             * resultar afectada.
             */
            $this->assertDatabaseHas('hu05_examenes_guard_test', [
                'asignatura_id' => $asignaturaId,
                'referencia' => 'EXAMEN-HU05-001',
            ]);
        } finally {
            Schema::dropIfExists('hu05_examenes_guard_test');
        }
    }
}
