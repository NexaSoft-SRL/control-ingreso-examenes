<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\IngresosRecientesGateway;
use App\Modules\Ingreso\Application\Queries\ConsultarIngresosRecientes;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IngresosRecientesTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_filtra_por_examen_y_aula(): void
    {
        $cuenta = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aulaA = $this->aula('P-101');
        $aulaB = $this->aula('P-102');
        [$uno, $dos] = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($docente, [$grupo], [$aulaA, $aulaB]);

        DB::table('ingresos')->insert([
            [
                'examen_id' => $examen->id,
                'estudiante_id' => $uno->id,
                'aula_id' => $aulaA->id,
                'registrado_por' => $cuenta->id,
                'registrado_en' => '2026-10-20 08:10:00',
            ],
            [
                'examen_id' => $examen->id,
                'estudiante_id' => $dos->id,
                'aula_id' => $aulaB->id,
                'registrado_por' => $cuenta->id,
                'registrado_en' => '2026-10-20 08:20:00',
            ],
        ]);

        $gateway = app(IngresosRecientesGateway::class);

        $filas = $gateway->recientes($examen->id, $aulaA->id, 20);

        self::assertCount(1, $filas);
        self::assertSame($uno->id, $filas[0]['estudiante_id']);
        self::assertSame(
            $uno->codigo_universitario,
            $filas[0]['codigo'],
        );
        self::assertSame($aulaA->id, $filas[0]['aula_id']);
    }

    public function test_consulta_rechazada_no_lee_el_gateway(): void
    {
        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->method('puedeOperar')->willReturn(false);

        $ingresos = $this->createMock(IngresosRecientesGateway::class);
        $ingresos->expects(self::never())->method('recientes');

        $consulta = new ConsultarIngresosRecientes(
            $alcance,
            $ingresos,
        );

        $this->expectException(AccesoPuntoDenegado::class);

        $consulta->ejecutar(10, 5, 3);
    }

    public function test_limite_fuera_de_rango_es_rechazado(): void
    {
        $consulta = app(ConsultarIngresosRecientes::class);

        $this->expectException(InvalidArgumentException::class);

        $consulta->ejecutar(1, 2, 3, 51);
    }

    public function test_politica_real_no_revela_registros(): void
    {
        $consulta = app(ConsultarIngresosRecientes::class);

        $this->expectException(AccesoPuntoDenegado::class);

        $consulta->ejecutar(1, 2, 3);
    }

    public function test_ordena_por_fecha_y_limita_resultados(): void
    {
        $cuenta = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($cuenta);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-201');
        [$primero, $segundo, $tercero] =
            $this->estudiantesInscritos($grupo, 3);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $ids = [];

        foreach ([
            [$primero, '2026-10-20 08:00:00'],
            [$segundo, '2026-10-20 08:15:00'],
            [$tercero, '2026-10-20 08:15:00'],
        ] as [$estudiante, $fecha]) {
            $ids[] = DB::table('ingresos')->insertGetId([
                'examen_id' => $examen->id,
                'estudiante_id' => $estudiante->id,
                'aula_id' => $aula->id,
                'registrado_por' => $cuenta->id,
                'registrado_en' => $fecha,
            ]);
        }

        $filas = app(IngresosRecientesGateway::class)
            ->recientes($examen->id, $aula->id, 2);

        self::assertCount(2, $filas);
        self::assertSame(
            [$ids[2], $ids[1]],
            array_column($filas, 'ingreso_id'),
        );
    }

    public function test_operador_autorizado_puede_consultar(): void
    {
        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->expects(self::once())
            ->method('puedeOperar')
            ->with(10, 5, 3)
            ->willReturn(true);

        $ingresos = $this->createMock(IngresosRecientesGateway::class);
        $ingresos->expects(self::once())
            ->method('recientes')
            ->with(5, 3, 20)
            ->willReturn([]);

        $consulta = new ConsultarIngresosRecientes(
            $alcance,
            $ingresos,
        );

        self::assertSame([], $consulta->ejecutar(10, 5, 3));
    }
}
