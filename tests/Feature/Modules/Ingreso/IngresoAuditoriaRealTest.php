<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IngresoAuditoriaRealTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_ingreso_y_bitacora_se_persisten_juntos(): void
    {
        [$examenId, $estudianteId, $aulaId, $usuarioId] =
            $this->escenario();

        self::assertTrue(app(IngresoGateway::class)->registrar(
            $examenId,
            $estudianteId,
            $aulaId,
            $usuarioId,
        ));

        $ingresoId = DB::table('ingresos')
            ->where('examen_id', $examenId)
            ->where('estudiante_id', $estudianteId)
            ->value('id');

        self::assertIsInt($ingresoId);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $usuarioId,
            'operacion' => 'INGRESO_ADMITIDO',
            'tabla_afectada' => 'ingresos',
            'registro_id' => $ingresoId,
        ]);

        $this->assertDatabaseCount('ingresos', 1);
        $this->assertDatabaseCount('bitacora_operaciones', 1);
    }

    public function test_duplicado_no_duplica_auditoria_real(): void
    {
        [$examenId, $estudianteId, $aulaId, $usuarioId] =
            $this->escenario();

        $gateway = app(IngresoGateway::class);

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

        $this->assertDatabaseCount('ingresos', 1);
        $this->assertDatabaseCount('bitacora_operaciones', 1);
    }

    public function test_fallo_sql_real_de_bitacora_revierte_ingreso(): void
    {
        [$examenId, $estudianteId, $aulaId, $usuarioId] =
            $this->escenario();

        // Solo dentro de la transaccion de la base de pruebas.
        // Obliga a PostgreSQL a rechazar esta operacion concreta.
        DB::statement(
            "ALTER TABLE bitacora_operaciones
             ADD CONSTRAINT chk_test_rechazar_admision
             CHECK (operacion <> 'INGRESO_ADMITIDO')"
        );

        try {
            app(IngresoGateway::class)->registrar(
                $examenId,
                $estudianteId,
                $aulaId,
                $usuarioId,
            );

            self::fail('Se esperaba un error de PostgreSQL.');
        } catch (QueryException $error) {
            self::assertSame('23514', $error->getCode());
        }

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseCount('bitacora_operaciones', 0);

        // Comprueba que la conexion sigue utilizable.
        $conexionActiva = DB::scalar('SELECT 1');

        self::assertTrue(
            is_int($conexionActiva) || is_string($conexionActiva),
        );

        self::assertSame('1', (string) $conexionActiva);
    }

    /**
     * @return array{int, int, int, int}
     */
    private function escenario(): array
    {
        $cuenta = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('AUD-201');

        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $examen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
        );

        return [
            $examen->id,
            $estudiante->id,
            $aula->id,
            $cuenta->id,
        ];
    }
}
