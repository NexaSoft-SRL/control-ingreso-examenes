<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class EstadoExpulsionGatewayTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_sin_expulsion_devuelve_false(): void
    {
        [$ingresoId, $examenId, $estudianteId] = $this->ingreso();

        self::assertGreaterThan(0, $ingresoId);

        self::assertFalse(
            app(EstadoExpulsionGateway::class)
                ->estaExpulsado($examenId, $estudianteId)
        );
    }

    public function test_expulsion_con_motivo_bloquea_al_estudiante(): void
    {
        [$ingresoId, $examenId, $estudianteId, $usuarioId] =
            $this->ingreso();

        DB::table('expulsiones')->insert([
            'ingreso_id' => $ingresoId,
            'registrada_por' => $usuarioId,
            'motivo' => 'Incumplimiento de normas.',
        ]);

        self::assertTrue(
            app(EstadoExpulsionGateway::class)
                ->estaExpulsado($examenId, $estudianteId)
        );

        $this->assertDatabaseHas('expulsiones', [
            'ingreso_id' => $ingresoId,
            'registrada_por' => $usuarioId,
            'motivo' => 'Incumplimiento de normas.',
        ]);
    }

    public function test_no_permite_dos_expulsiones_del_mismo_ingreso(): void
    {
        [$ingresoId, , , $usuarioId] = $this->ingreso();

        $fila = [
            'ingreso_id' => $ingresoId,
            'registrada_por' => $usuarioId,
            'motivo' => 'Motivo documentado.',
        ];

        DB::table('expulsiones')->insert($fila);

        try {
            DB::transaction(static function () use ($fila): void {
                DB::table('expulsiones')->insert($fila);
            });
            self::fail('Se esperaba una violación de unicidad.');
        } catch (QueryException $error) {
            self::assertSame('23505', $error->getCode());
        }

        $this->assertDatabaseCount('expulsiones', 1);
    }

    public function test_no_acepta_motivo_vacio(): void
    {
        [$ingresoId, , , $usuarioId] = $this->ingreso();

        try {
            DB::transaction(static function () use (
                $ingresoId,
                $usuarioId
            ): void {
                DB::table('expulsiones')->insert([
                    'ingreso_id' => $ingresoId,
                    'registrada_por' => $usuarioId,
                    'motivo' => '   ',
                ]);
            });
            self::fail('Se esperaba una violación del CHECK.');
        } catch (QueryException $error) {
            self::assertSame('23514', $error->getCode());
        }

        $this->assertDatabaseCount('expulsiones', 0);
    }

    /**
     * @return array{int, int, int, int}
     */
    private function ingreso(): array
    {
        $cuenta = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-101');
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $ingresoId = DB::table('ingresos')->insertGetId([
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula->id,
            'registrado_por' => $cuenta->id,
        ]);

        return [
            $ingresoId,
            $examen->id,
            $estudiante->id,
            $cuenta->id,
        ];
    }
}
