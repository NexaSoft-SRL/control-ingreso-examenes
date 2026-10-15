<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IngresoGatewayTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_registra_ingreso_con_autor_aula_y_fecha(): void
    {
        [$gateway, $examenId, $estudianteId, $aulaId, $usuarioId] =
            $this->escenario();

        self::assertFalse($gateway->yaIngreso($examenId, $estudianteId));

        self::assertTrue($gateway->registrar(
            $examenId,
            $estudianteId,
            $aulaId,
            $usuarioId,
        ));

        self::assertTrue($gateway->yaIngreso($examenId, $estudianteId));

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examenId,
            'estudiante_id' => $estudianteId,
            'aula_id' => $aulaId,
            'registrado_por' => $usuarioId,
        ]);

        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_duplicado_devuelve_false_y_conserva_el_original(): void
    {
        [$gateway, $examenId, $estudianteId, $aulaId, $usuarioId] =
            $this->escenario();

        self::assertTrue($gateway->registrar(
            $examenId,
            $estudianteId,
            $aulaId,
            $usuarioId,
        ));

        self::assertFalse($gateway->registrar(
            $examenId,
            $estudianteId,
            $aulaId,
            $usuarioId,
        ));

        // Comprueba que la transacción exterior sigue siendo utilizable.
        self::assertTrue($gateway->yaIngreso($examenId, $estudianteId));

        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_estudiante_puede_ingresar_a_examenes_distintos(): void
    {
        [$gateway, $examenId, $estudianteId, $aulaId, $usuarioId] =
            $this->escenario();

        $docente = $this->docenteConCuenta(
            $this->usuarioConPermisos(['examenes']),
        );
        $grupo = $this->grupo($docente);
        $otroExamen = $this->examen($docente, [$grupo], []);

        self::assertTrue($gateway->registrar(
            $examenId,
            $estudianteId,
            $aulaId,
            $usuarioId,
        ));

        self::assertTrue($gateway->registrar(
            $otroExamen->id,
            $estudianteId,
            $aulaId,
            $usuarioId,
        ));

        $this->assertDatabaseCount('ingresos', 2);
    }

    public function test_clave_foranea_invalida_no_se_confunde_con_duplicado(): void
    {
        [$gateway, $examenId, $estudianteId, $aulaId] =
            $this->escenario();

        $this->expectException(QueryException::class);

        $gateway->registrar(
            $examenId,
            $estudianteId,
            $aulaId,
            -999999,
        );
    }

    /**
     * @return array{IngresoGateway, int, int, int, int}
     */
    private function escenario(): array
    {
        $cuenta = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-101');
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        return [
            app(IngresoGateway::class),
            $examen->id,
            $estudiante->id,
            $aula->id,
            $cuenta->id,
        ];
    }
}
