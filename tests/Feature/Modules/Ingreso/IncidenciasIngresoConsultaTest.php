<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\IncidenciasIngresoGateway;
use App\Modules\Ingreso\Application\Queries\ConsultarIncidenciasIngreso;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IncidenciasIngresoConsultaTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_consulta_real_filtra_por_aula_y_recupera_identidad_y_detalle(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $aulaA = $this->aula('INC-A');
        $aulaB = $this->aula('INC-B');
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $examen = $this->examen(
            $docente,
            [$grupo],
            [$aulaA, $aulaB],
        );

        DB::table('incidencias_ingreso')->insert([
            [
                'examen_id' => $examen->id,
                'aula_id' => $aulaA->id,
                'estudiante_id' => $estudiante->id,
                'registrado_por' => $usuario->id,
                'tipo_rechazo' => 'NO_HABILITADO',
                'detalle' => 'Documentación pendiente.',
                'registrado_en' => '2026-10-16 08:00:00',
            ],
            [
                'examen_id' => $examen->id,
                'aula_id' => $aulaB->id,
                'estudiante_id' => $estudiante->id,
                'registrado_por' => $usuario->id,
                'tipo_rechazo' => 'OTRA_AULA',
                'detalle' => 'Aula distinta.',
                'registrado_en' => '2026-10-16 08:01:00',
            ],
            [
                'examen_id' => $examen->id,
                'aula_id' => $aulaA->id,
                'estudiante_id' => null,
                'registrado_por' => $usuario->id,
                'tipo_rechazo' => 'NO_CORRESPONDE',
                'detalle' => 'Estudiante no encontrado.',
                'registrado_en' => '2026-10-16 08:02:00',
            ],
        ]);

        $filas = app(IncidenciasIngresoGateway::class)
            ->recientes($examen->id, $aulaA->id, 20);

        self::assertCount(2, $filas);

        self::assertSame('NO_CORRESPONDE', $filas[0]['tipo_rechazo']);
        self::assertNull($filas[0]['estudiante_id']);
        self::assertNull($filas[0]['codigo']);
        self::assertNull($filas[0]['nombres']);
        self::assertSame(
            'Estudiante no encontrado.',
            $filas[0]['detalle'],
        );

        self::assertSame('NO_HABILITADO', $filas[1]['tipo_rechazo']);
        self::assertSame($estudiante->id, $filas[1]['estudiante_id']);
        self::assertSame(
            $estudiante->codigo_universitario,
            $filas[1]['codigo'],
        );
        self::assertSame($estudiante->nombres, $filas[1]['nombres']);
        self::assertSame($estudiante->apellidos, $filas[1]['apellidos']);
        self::assertSame('Documentación pendiente.', $filas[1]['detalle']);
        self::assertSame($aulaA->id, $filas[1]['aula_id']);
        self::assertNotEmpty($filas[1]['registrado_en']);
        self::assertNotEmpty($filas[1]['operador']);

        $limitadas = app(IncidenciasIngresoGateway::class)
            ->recientes($examen->id, $aulaA->id, 1);

        self::assertCount(1, $limitadas);
        self::assertSame(
            $filas[0]['incidencia_id'],
            $limitadas[0]['incidencia_id'],
        );
    }

    public function test_operador_sin_alcance_no_consulta_gateway(): void
    {
        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->method('puedeOperar')->willReturn(false);

        $gateway = $this->createMock(IncidenciasIngresoGateway::class);
        $gateway->expects(self::never())->method('recientes');

        $consulta = new ConsultarIncidenciasIngreso(
            $alcance,
            $gateway,
        );

        $this->expectException(AccesoPuntoDenegado::class);

        $consulta->ejecutar(10, 5, 3);
    }

    public function test_operador_autorizado_consulta_examen_y_aula_solicitados(): void
    {
        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->expects(self::once())
            ->method('puedeOperar')
            ->with(10, 5, 3)
            ->willReturn(true);

        $gateway = $this->createMock(IncidenciasIngresoGateway::class);
        $gateway->expects(self::once())
            ->method('recientes')
            ->with(5, 3, 20)
            ->willReturn([]);

        $consulta = new ConsultarIncidenciasIngreso(
            $alcance,
            $gateway,
        );

        self::assertSame([], $consulta->ejecutar(10, 5, 3));
    }

    public function test_limite_invalido_se_rechaza_antes_de_leer(): void
    {
        $gateway = $this->createMock(IncidenciasIngresoGateway::class);
        $gateway->expects(self::never())->method('recientes');

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->expects(self::never())->method('puedeOperar');

        $consulta = new ConsultarIncidenciasIngreso(
            $alcance,
            $gateway,
        );

        $this->expectException(InvalidArgumentException::class);

        $consulta->ejecutar(10, 5, 3, 51);
    }
}
