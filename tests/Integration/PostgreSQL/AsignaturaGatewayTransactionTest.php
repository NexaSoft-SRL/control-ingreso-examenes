<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use App\Modules\Examenes\Application\Contracts\AsignaturaGateway;
use App\Modules\Examenes\Application\DTOs\GrupoAsignaturaData;
use App\Modules\Examenes\Application\DTOs\RegistrarAsignaturaData;
use App\Modules\Examenes\Domain\Models\Docente;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AsignaturaGatewayTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rolls_back_everything_when_one_group_cannot_be_persisted(): void
    {
        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-ROLLBACK-001',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'estado' => true,
        ]);

        $docenteId = $docente->getKey();

        if (! is_int($docenteId)) {
            $this->fail(
                'El identificador PostgreSQL del docente debía ser un entero.'
            );
        }

        /** @var AsignaturaGateway $gateway */
        $gateway = $this->app->make(AsignaturaGateway::class);

        $data = new RegistrarAsignaturaData(
            codigo: 'INF-ROLLBACK',
            nombre: 'Asignatura transaccional',
            semestre: '6',
            descripcion: 'Prueba real de rollback PostgreSQL.',
            grupos: [
                new GrupoAsignaturaData(
                    codigoGrupo: '1',
                    docenteId: $docenteId,
                    cupo: 30,
                ),
                new GrupoAsignaturaData(
                    codigoGrupo: '2',
                    docenteId: 999999999,
                    cupo: 30,
                ),
            ],
        );

        try {
            $gateway->registrar($data);

            $this->fail(
                'PostgreSQL debía rechazar el segundo grupo por su docente inexistente.'
            );
        } catch (QueryException) {
            // PostgreSQL rechaza el segundo grupo por violación de clave foránea.
        }

        $this->assertDatabaseMissing('asignaturas', [
            'codigo' => 'INF-ROLLBACK',
        ]);

        $this->assertDatabaseCount('asignaturas', 0);

        $this->assertDatabaseCount('grupos_asignatura', 0);

        $this->assertDatabaseHas('docentes', [
            'id' => $docenteId,
            'codigo_docente' => 'DOC-ROLLBACK-001',
        ]);
    }
}
