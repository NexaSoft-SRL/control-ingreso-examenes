<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ingreso;

use App\Modules\Estudiantes\Application\Contracts\InscritosDeExamenGateway;
use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\ExamenActualData;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\Queries\ConsultarCandidato;
use App\Modules\Ingreso\Application\Queries\ResolverExamenEnPuerta;
use App\Modules\Ingreso\Application\Queries\ResolverIngresoAutorizadoEnPuerta;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Domain\Rules\EvaluarIngreso;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResolverIngresoAutorizadoEnPuertaTest extends TestCase
{
    /**
     * @param  list<int>  $aulas
     */
    private function servicio(
        bool $autorizado = true,
        bool $abierto = true,
        array $aulas = [30],
    ): ResolverIngresoAutorizadoEnPuerta {
        $examenes = $this->createMock(ExamenGateway::class);

        $examenes->method('actual')->willReturn(
            new ExamenActualData(
                5, 1, 2, 'PARCIAL',
                '2026-10-20', '08:00:00',
                120, [12], $aulas,
            )
        );

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->method('puedeOperar')->willReturn($autorizado);

        $ventana = $this->createMock(VentanaPuntoControl::class);
        $ventana->method('estaAbierta')->willReturn($abierto);

        $padron = $this->createMock(PadronGateway::class);
        $padron->method('porCodigos')->willReturn([]);

        $resolver = new ResolverExamenEnPuerta(
            new ConsultarCandidato(
                $padron,
                $this->createMock(InscritosDeExamenGateway::class),
                $examenes,
                $this->createMock(HabilitacionGateway::class),
            ),
            new EvaluarIngreso,
            $this->createMock(IngresoGateway::class),
            $this->createMock(EstadoExpulsionGateway::class),
        );

        return new ResolverIngresoAutorizadoEnPuerta(
            $examenes,
            $alcance,
            $ventana,
            $resolver,
        );
    }

    public function test_deniega_aula_no_autorizada(): void
    {
        $this->expectException(AccesoPuntoDenegado::class);

        $this->servicio(autorizado: false)->ejecutar(
            7, 30, [5], 'codigo', '20260017',
        );
    }

    public function test_deniega_aula_ajena_al_examen(): void
    {
        $this->expectException(AccesoPuntoDenegado::class);

        $this->servicio(aulas: [40])->ejecutar(
            7, 30, [5], 'codigo', '20260017',
        );
    }

    public function test_rechaza_examen_fuera_de_horario(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sin examen a esta hora.');

        $this->servicio(abierto: false)->ejecutar(
            7, 30, [5], 'codigo', '20260017',
        );
    }

    public function test_autorizado_sin_coincidencia_no_admite(): void
    {
        $resultado = $this->servicio()->ejecutar(
            7, 30, [5], 'codigo', '20260017',
        );

        self::assertNull($resultado->examenId);
        self::assertFalse($resultado->puedeAdmitir());
    }

    public function test_rechaza_identificadores_invalidos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio()->ejecutar(
            0, 30, [5], 'codigo', '20260017',
        );
    }

    public function test_ignora_examen_ajeno_y_resuelve_el_autorizado(): void
    {
        $examenes = $this->createMock(ExamenGateway::class);

        $examenes->method('actual')->willReturnCallback(
            static fn (int $id): ExamenActualData => new ExamenActualData(
                $id,
                1,
                2,
                'PARCIAL',
                '2026-10-20',
                '08:00:00',
                120,
                [12],
                [30],
            )
        );

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->method('puedeOperar')->willReturnCallback(
            static fn (int $usuario, int $examen, int $aula): bool => $usuario === 7 && $examen === 5 && $aula === 30
        );

        $ventana = $this->createMock(VentanaPuntoControl::class);
        $ventana->expects(self::once())
            ->method('estaAbierta')
            ->with(5)
            ->willReturn(true);

        $padron = $this->createMock(PadronGateway::class);
        $padron->method('porCodigos')->willReturn([]);

        $resolver = new ResolverExamenEnPuerta(
            new ConsultarCandidato(
                $padron,
                $this->createMock(InscritosDeExamenGateway::class),
                $examenes,
                $this->createMock(HabilitacionGateway::class),
            ),
            new EvaluarIngreso,
            $this->createMock(IngresoGateway::class),
            $this->createMock(EstadoExpulsionGateway::class),
        );

        $servicio = new ResolverIngresoAutorizadoEnPuerta(
            $examenes,
            $alcance,
            $ventana,
            $resolver,
        );

        $resultado = $servicio->ejecutar(
            7,
            30,
            [6, 5],
            'codigo',
            '20260017',
        );

        self::assertNull($resultado->examenId);
        self::assertFalse($resultado->puedeAdmitir());
    }
}
