<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\IncidenciaIngresoGateway;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IncidenciasEnPuertaHttpTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const RUTA = '/api/aulas/1/punto-control/incidencias';

    public function test_requiere_autenticacion(): void
    {
        $this->getJson(self::RUTA)
            ->assertUnauthorized();
    }

    public function test_requiere_permiso(): void
    {
        $usuario = $this->usuarioConPermisos([]);

        $this->actingAs($usuario)
            ->getJson(self::RUTA)
            ->assertForbidden();
    }

    public function test_no_entrega_incidencias_sin_asignacion(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->getJson(self::RUTA)
            ->assertForbidden();
    }

    public function test_valida_limite_de_resultados(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->getJson(self::RUTA.'?limite=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limite');
    }

    public function test_consolida_incidencias_y_excluye_examen_no_autorizado(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-401');

        $primero = $this->examen($docente, [$grupo], [$aula]);
        $segundo = $this->examen($docente, [$grupo], [$aula]);
        $prohibido = $this->examen($docente, [$grupo], [$aula]);

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $permitidos = [$primero->id, $segundo->id];

        $alcance->method('puedeOperar')
            ->willReturnCallback(
                static fn (
                    int $usuarioId,
                    int $examenId,
                    int $aulaId,
                ): bool => $usuarioId === $usuario->id
                    && in_array($examenId, $permitidos, true)
                    && $aulaId === $aula->id,
            );

        $this->app->instance(AlcancePuntoControl::class, $alcance);

        $auditoria = app(IncidenciaIngresoGateway::class);

        $auditoria->registrar(
            $primero->id,
            $aula->id,
            null,
            $usuario->id,
            VeredictoIngreso::NoCorresponde,
            'Incidencia permitida A.',
        );

        $auditoria->registrar(
            $segundo->id,
            $aula->id,
            null,
            $usuario->id,
            VeredictoIngreso::NoCorresponde,
            'Incidencia permitida B.',
        );

        $auditoria->registrar(
            $prohibido->id,
            $aula->id,
            null,
            $usuario->id,
            VeredictoIngreso::NoCorresponde,
            'Incidencia confidencial.',
        );

        $respuesta = $this->actingAs($usuario)
            ->getJson("/api/aulas/{$aula->id}/punto-control/incidencias")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.cantidad', 2);

        $filas = $respuesta->json('data');
        self::assertIsArray($filas);

        $ids = [];

        foreach ($filas as $fila) {
            self::assertIsArray($fila);
            $id = $fila['examen_id'];
            self::assertIsInt($id);
            $ids[] = $id;

            self::assertNotSame(
                'Incidencia confidencial.',
                $fila['detalle'],
            );
        }

        self::assertEqualsCanonicalizing($permitidos, $ids);
    }
}
