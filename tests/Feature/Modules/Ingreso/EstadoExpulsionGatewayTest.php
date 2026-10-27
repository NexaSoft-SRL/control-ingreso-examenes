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

        DB::table('incidencias')->insert([
            ...self::expulsion($ingresoId, $usuarioId),
            'detalle' => 'Incumplimiento de normas.',
        ]);

        self::assertTrue(
            app(EstadoExpulsionGateway::class)
                ->estaExpulsado($examenId, $estudianteId)
        );

        $this->assertDatabaseHas('incidencias', [
            'ingreso_id' => $ingresoId,
            'reportada_por' => $usuarioId,
            'detalle' => 'Incumplimiento de normas.',
            'tipo' => 'EXPULSION',
        ]);
    }

    public function test_no_permite_dos_expulsiones_del_mismo_ingreso(): void
    {
        [$ingresoId, , , $usuarioId] = $this->ingreso();

        $fila = [
            ...self::expulsion($ingresoId, $usuarioId),
            'detalle' => 'Motivo documentado.',
        ];

        DB::table('incidencias')->insert($fila);

        try {
            DB::transaction(static function () use ($fila): void {
                DB::table('incidencias')->insert($fila);
            });
            self::fail('Se esperaba una violación de unicidad.');
        } catch (QueryException $error) {
            self::assertSame('23505', $error->getCode());
        }

        $this->assertSame(1, DB::table('incidencias')->where('tipo', 'EXPULSION')->count());
    }

    public function test_no_acepta_motivo_vacio(): void
    {
        [$ingresoId, , , $usuarioId] = $this->ingreso();

        try {
            DB::transaction(static function () use (
                $ingresoId,
                $usuarioId
            ): void {
                DB::table('incidencias')->insert([
                    ...self::expulsion($ingresoId, $usuarioId),
                    'detalle' => '   ',
                ]);
            });
            self::fail('Se esperaba una violación del CHECK.');
        } catch (QueryException $error) {
            self::assertSame('23514', $error->getCode());
        }

        $this->assertSame(0, DB::table('incidencias')->where('tipo', 'EXPULSION')->count());
    }

    /**
     * La expulsion es una incidencia del ingreso.
     *
     * @return array<string, mixed>
     */
    private static function expulsion(int $ingresoId, int $usuarioId): array
    {
        return [
            'ingreso_id' => $ingresoId,
            'reportada_por' => $usuarioId,
            'tipo' => 'EXPULSION',
            'origen' => 'PERSONA',
            'ocurrida_en' => now(),
        ];
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
