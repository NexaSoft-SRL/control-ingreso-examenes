<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Infrastructure\Persistence\IngresoAuditadoGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IngresoAuditoriaTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_registro_confirmado_genera_una_auditoria(): void
    {
        [$examenId, $estudianteId, $aulaId, $usuarioId] =
            $this->escenario();

        $bitacora = $this->createMock(BitacoraGateway::class);

        $bitacora->expects(self::once())
            ->method('registrar')
            ->with(
                $usuarioId,
                'INGRESO_ADMITIDO',
                'ingresos',
                self::callback(static fn (mixed $id): bool => (
                    is_int($id) && $id > 0
                )),
                self::callback(
                    static fn (mixed $descripcion): bool => is_string($descripcion)
                        && str_contains($descripcion, "Examen {$examenId}")
                        && str_contains(
                            $descripcion,
                            "estudiante {$estudianteId}",
                        )
                        && str_contains($descripcion, "aula {$aulaId}"),
                ),
            );

        $this->app->instance(BitacoraGateway::class, $bitacora);

        $gateway = app(IngresoGateway::class);

        self::assertInstanceOf(IngresoAuditadoGateway::class, $gateway);
        self::assertTrue(
            $gateway->registrar(
                $examenId,
                $estudianteId,
                $aulaId,
                $usuarioId,
            ),
        );

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examenId,
            'estudiante_id' => $estudianteId,
            'aula_id' => $aulaId,
            'registrado_por' => $usuarioId,
        ]);
    }

    public function test_fallo_de_bitacora_revierte_el_ingreso(): void
    {
        [$examenId, $estudianteId, $aulaId, $usuarioId] =
            $this->escenario();

        $bitacora = $this->createMock(BitacoraGateway::class);
        $bitacora->expects(self::once())
            ->method('registrar')
            ->willThrowException(
                new RuntimeException('Bitácora indisponible.'),
            );

        $this->app->instance(BitacoraGateway::class, $bitacora);

        try {
            app(IngresoGateway::class)->registrar(
                $examenId,
                $estudianteId,
                $aulaId,
                $usuarioId,
            );

            self::fail('Se esperaba el fallo de bitácora.');
        } catch (RuntimeException $error) {
            self::assertSame(
                'Bitácora indisponible.',
                $error->getMessage(),
            );
        }

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_duplicado_no_genera_otra_auditoria(): void
    {
        [$examenId, $estudianteId, $aulaId, $usuarioId] =
            $this->escenario();

        $bitacora = $this->createMock(BitacoraGateway::class);
        $bitacora->expects(self::once())
            ->method('registrar');

        $this->app->instance(BitacoraGateway::class, $bitacora);

        $gateway = app(IngresoGateway::class);

        self::assertTrue(
            $gateway->registrar(
                $examenId,
                $estudianteId,
                $aulaId,
                $usuarioId,
            ),
        );

        self::assertFalse(
            $gateway->registrar(
                $examenId,
                $estudianteId,
                $aulaId,
                $usuarioId,
            ),
        );

        $this->assertDatabaseCount('ingresos', 1);
    }

    /**
     * @return array{int, int, int, int}
     */
    private function escenario(): array
    {
        $cuenta = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('AUD-101');
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        return [
            $examen->id,
            $estudiante->id,
            $aula->id,
            $cuenta->id,
        ];
    }
}
