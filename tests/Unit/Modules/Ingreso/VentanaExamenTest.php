<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ingreso;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\ExamenActualData;
use App\Modules\Ingreso\Domain\Rules\ComprobarVentanaIngreso;
use App\Modules\Ingreso\Infrastructure\Persistence\VentanaExamen;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class VentanaExamenTest extends TestCase
{
    public function test_examen_inexistente_tiene_ventana_cerrada(): void
    {
        $examenes = $this->createMock(ExamenGateway::class);

        $examenes->expects(self::once())
            ->method('actual')
            ->with(7)
            ->willReturn(null);

        $ventana = new VentanaExamen(
            $examenes,
            new ComprobarVentanaIngreso,
        );

        self::assertFalse($ventana->estaAbierta(7));
    }

    public function test_consulta_la_fecha_y_hora_del_examen(): void
    {
        $zona = new DateTimeZone('America/La_Paz');

        $inicio = new DateTimeImmutable('now', $zona);
        $inicio = $inicio->modify('-5 minutes');

        $examen = new ExamenActualData(
            7,
            1,
            3,
            'PRIMER_PARCIAL',
            $inicio->format('Y-m-d'),
            $inicio->format('H:i:s'),
            120,
            [12],
            [30],
        );

        $examenes = $this->createMock(ExamenGateway::class);

        $examenes->expects(self::once())
            ->method('actual')
            ->with(7)
            ->willReturn($examen);

        $ventana = new VentanaExamen(
            $examenes,
            new ComprobarVentanaIngreso,
        );

        self::assertTrue($ventana->estaAbierta(7));
    }
}
