<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IngresosRecientesEnPuertaHttpTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const RUTA = '/api/aulas/1/punto-control/ingresos';

    public function test_requiere_autenticacion(): void
    {
        $this->getJson(self::RUTA)->assertUnauthorized();
    }

    public function test_requiere_permiso_punto_control(): void
    {
        $usuario = $this->usuarioConPermisos([]);

        $this->actingAs($usuario)
            ->getJson(self::RUTA)
            ->assertForbidden();
    }

    public function test_no_revela_ingresos_sin_asignacion(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->getJson(self::RUTA)
            ->assertForbidden();
    }

    public function test_consolida_cinco_ingresos_de_dos_examenes(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-301');

        $estudiantes = $this->estudiantesInscritos($grupo, 6);

        $primero = $this->examen($docente, [$grupo], [$aula]);
        $segundo = $this->examen($docente, [$grupo], [$aula]);

        $examenes = [$primero, $segundo];

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $ids = [$primero->id, $segundo->id];

        $alcance->method('puedeOperar')
            ->willReturnCallback(
                static fn (
                    int $usuarioId,
                    int $examenId,
                    int $aulaId,
                ): bool => $usuarioId === $usuario->id
                    && in_array($examenId, $ids, true)
                    && $aulaId === $aula->id,
            );

        $this->app->instance(AlcancePuntoControl::class, $alcance);

        foreach ($estudiantes as $indice => $estudiante) {
            $examen = $examenes[$indice % 2];

            $id = $this->ingresar($examen, $estudiante, $aula);

            DB::table('ingresos')
                ->where('id', $id)
                ->update([
                    'registrado_en' => now()->subMinutes(6 - $indice),
                ]);
        }

        $respuesta = $this->actingAs($usuario)
            ->getJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
            )
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.cantidad', 5);

        $filas = $respuesta->json('data');

        self::assertIsArray($filas);

        /** @var list<array<string, mixed>> $filas */
        self::assertCount(5, $filas);

        $codigos = array_column($filas, 'codigo');

        self::assertNotContains(
            $estudiantes[0]->codigo_universitario,
            $codigos,
        );

        self::assertSame(
            $estudiantes[5]->codigo_universitario,
            $codigos[0],
        );

        self::assertSame(
            $estudiantes[1]->codigo_universitario,
            $codigos[4],
        );

        $examenesEncontrados = array_map(
            static function (array $fila): int {
                $examenId = $fila['examen_id'];

                self::assertIsInt($examenId);

                return $examenId;
            },
            $filas,
        );

        self::assertEqualsCanonicalizing(
            [$primero->id, $segundo->id],
            array_values(array_unique($examenesEncontrados)),
        );
    }

    public function test_excluye_ingresos_de_examen_no_autorizado(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-302');

        $estudiantes = $this->estudiantesInscritos($grupo, 2);

        $permitido = $this->examen($docente, [$grupo], [$aula]);
        $prohibido = $this->examen($docente, [$grupo], [$aula]);

        $this->ingresar($permitido, $estudiantes[0], $aula);
        $this->ingresar($prohibido, $estudiantes[1], $aula);

        $alcance = $this->createMock(AlcancePuntoControl::class);

        $alcance->method('puedeOperar')
            ->willReturnCallback(
                static fn (
                    int $usuarioId,
                    int $examenId,
                    int $aulaId,
                ): bool => $usuarioId === $usuario->id
                    && $examenId === $permitido->id
                    && $aulaId === $aula->id,
            );

        $this->app->instance(AlcancePuntoControl::class, $alcance);

        $respuesta = $this->actingAs($usuario)
            ->getJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
            )
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $respuesta->assertJsonPath(
            'data.0.examen_id',
            $permitido->id,
        );

        $filas = $respuesta->json('data');
        self::assertIsArray($filas);

        foreach ($filas as $fila) {
            self::assertIsArray($fila);
            self::assertSame($permitido->id, $fila['examen_id']);
        }

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $prohibido->id,
            'estudiante_id' => $estudiantes[1]->id,
        ]);
    }
}
