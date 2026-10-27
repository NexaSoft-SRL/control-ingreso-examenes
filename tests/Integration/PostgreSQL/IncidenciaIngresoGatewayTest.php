<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use App\Modules\Ingreso\Application\Contracts\IncidenciaIngresoGateway;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IncidenciaIngresoGatewayTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_rechazo_persiste_motivo_y_no_crea_ingreso(): void
    {
        [$examen, $estudiante, $aula, $usuario] = $this->escenario();

        app(IncidenciaIngresoGateway::class)->registrar(
            $examen,
            $aula,
            $estudiante,
            $usuario,
            VeredictoIngreso::NoHabilitado,
            'Documentación pendiente',
        );

        $this->assertDatabaseHas('incidencias', [
            'examen_id' => $examen,
            'aula_id' => $aula,
            'estudiante_id' => $estudiante,
            'reportada_por' => $usuario,
            'causa' => 'NO_HABILITADO',
            'detalle' => 'Documentación pendiente',
        ]);

        $this->assertDatabaseCount('incidencias', 1);
        $this->assertDatabaseCount('ingresos', 0);

        $this->assertNotNull(
            DB::table('incidencias')->value('ocurrida_en')
        );
    }

    public function test_permite_estudiante_no_identificado_y_multiples_intentos(): void
    {
        [$examen, , $aula, $usuario] = $this->escenario();

        $gateway = app(IncidenciaIngresoGateway::class);

        for ($i = 0; $i < 2; $i++) {
            $gateway->registrar(
                $examen,
                $aula,
                null,
                $usuario,
                VeredictoIngreso::NoCorresponde,
                'Estudiante no encontrado.',
            );
        }

        $this->assertDatabaseCount('incidencias', 2);
        $this->assertDatabaseCount('ingresos', 0);

        $this->assertSame(
            2,
            DB::table('incidencias')
                ->whereNull('estudiante_id')
                ->count()
        );
    }

    public function test_no_registra_admitido_como_rechazo(): void
    {
        [$examen, $estudiante, $aula, $usuario] = $this->escenario();

        $this->expectException(InvalidArgumentException::class);

        app(IncidenciaIngresoGateway::class)->registrar(
            $examen,
            $aula,
            $estudiante,
            $usuario,
            VeredictoIngreso::Admitido,
            'Ingreso autorizado',
        );
    }

    public function test_exige_detalle_no_vacio(): void
    {
        [$examen, $estudiante, $aula, $usuario] = $this->escenario();

        $this->expectException(InvalidArgumentException::class);

        app(IncidenciaIngresoGateway::class)->registrar(
            $examen,
            $aula,
            $estudiante,
            $usuario,
            VeredictoIngreso::NoHabilitado,
            '   ',
        );
    }

    /**
     * @return array{int, int, int, int}
     */
    private function escenario(): array
    {
        $cuenta = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('INC-201');

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
