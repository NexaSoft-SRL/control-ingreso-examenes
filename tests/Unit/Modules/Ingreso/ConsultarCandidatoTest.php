<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ingreso;

use App\Modules\Estudiantes\Application\Contracts\InscritosDeExamenGateway;
use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\ExamenActualData;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\HabilitacionData;
use App\Modules\Ingreso\Application\Queries\ConsultarCandidato;
use PHPUnit\Framework\TestCase;

final class ConsultarCandidatoTest extends TestCase
{
    private function estudiante(): EstudianteData
    {
        return new EstudianteData(
            17,
            '20260017',
            '9876543',
            'Ana',
            'Pérez',
            true,
        );
    }

    private function examen(): ExamenActualData
    {
        return new ExamenActualData(
            5,
            1,
            2,
            'PARCIAL',
            '2026-10-20',
            '08:00:00',
            120,
            [12],
            [30],
        );
    }

    public function test_consulta_por_codigo_y_recupera_habilitacion(): void
    {
        $estudiante = $this->estudiante();

        $condicion = new HabilitacionData(
            5,
            17,
            true,
            30,
            'Aula 30',
            null,
            null,
            null,
            null,
        );

        $padron = $this->createMock(PadronGateway::class);
        $inscritos = $this->createMock(InscritosDeExamenGateway::class);
        $examenes = $this->createMock(ExamenGateway::class);
        $habilitaciones = $this->createMock(HabilitacionGateway::class);

        $examenes->expects(self::once())
            ->method('actual')->with(5)
            ->willReturn($this->examen());

        $padron->expects(self::once())
            ->method('porCodigos')->with(['20260017'])
            ->willReturn(['20260017' => $estudiante]);

        $inscritos->expects(self::once())
            ->method('estudiantesDe')->with([12])
            ->willReturn([$estudiante]);

        $habilitaciones->expects(self::once())
            ->method('condicionDe')->with(5, 17)
            ->willReturn($condicion);

        $consulta = new ConsultarCandidato(
            $padron,
            $inscritos,
            $examenes,
            $habilitaciones,
        );

        $resultado = $consulta->consultar(5, 'codigo', ' 20260017 ');

        self::assertNotNull($resultado);
        self::assertTrue($resultado->inscrito);
        self::assertSame(17, $resultado->estudiante->id);
        self::assertSame($condicion, $resultado->habilitacion);
    }

    public function test_consulta_documento_y_detecta_no_inscrito(): void
    {
        $estudiante = $this->estudiante();

        $padron = $this->createMock(PadronGateway::class);
        $inscritos = $this->createMock(InscritosDeExamenGateway::class);
        $examenes = $this->createMock(ExamenGateway::class);
        $habilitaciones = $this->createMock(HabilitacionGateway::class);

        $examenes->method('actual')->willReturn($this->examen());

        $padron->expects(self::once())
            ->method('codigosPorDocumento')->with(['9876543'])
            ->willReturn(['9876543' => '20260017']);

        $padron->method('porCodigos')
            ->willReturn(['20260017' => $estudiante]);

        $inscritos->method('estudiantesDe')->willReturn([]);

        $habilitaciones->expects(self::never())
            ->method('condicionDe');

        $consulta = new ConsultarCandidato(
            $padron,
            $inscritos,
            $examenes,
            $habilitaciones,
        );

        $resultado = $consulta->consultar(5, 'documento', '9876543');

        self::assertNotNull($resultado);
        self::assertFalse($resultado->inscrito);
        self::assertNull($resultado->habilitacion);
    }

    public function test_rechaza_un_tipo_de_identificacion_invalido(): void
    {
        $consulta = new ConsultarCandidato(
            $this->createMock(PadronGateway::class),
            $this->createMock(InscritosDeExamenGateway::class),
            $this->createMock(ExamenGateway::class),
            $this->createMock(HabilitacionGateway::class),
        );

        $this->expectException(\InvalidArgumentException::class);

        $consulta->consultar(5, 'otro', '20260017');
    }
}
