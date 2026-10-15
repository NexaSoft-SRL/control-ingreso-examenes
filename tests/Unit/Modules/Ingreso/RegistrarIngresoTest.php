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
use App\Modules\Ingreso\Application\Actions\RegistrarIngreso;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\Queries\ConsultarCandidato;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Domain\Rules\EvaluarIngreso;
use DomainException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegistrarIngresoTest extends TestCase
{
    public function test_admision_confirmada_por_persistencia(): void
    {
        [$accion, $ingresos] = $this->escenario();

        $ingresos->method('yaIngreso')->willReturn(false);
        $ingresos->expects(self::once())
            ->method('registrar')
            ->with(7, 15, 30, 2)
            ->willReturn(true);

        self::assertSame(
            VeredictoIngreso::Admitido,
            $accion->ejecutar(2, 7, 30, 'codigo', '20260015'),
        );
    }

    public function test_conflicto_de_persistencia_no_admite_dos_veces(): void
    {
        [$accion, $ingresos] = $this->escenario();

        $ingresos->method('yaIngreso')->willReturn(false);
        $ingresos->method('registrar')->willReturn(false);

        self::assertSame(
            VeredictoIngreso::YaIngreso,
            $accion->ejecutar(2, 7, 30, 'codigo', '20260015'),
        );
    }

    public function test_ingreso_previo_no_intenta_registrar(): void
    {
        [$accion, $ingresos] = $this->escenario();

        $ingresos->method('yaIngreso')->willReturn(true);
        $ingresos->expects(self::never())->method('registrar');

        self::assertSame(
            VeredictoIngreso::YaIngreso,
            $accion->ejecutar(2, 7, 30, 'codigo', '20260015'),
        );
    }

    public function test_expulsado_no_puede_ingresar(): void
    {
        [$accion, $ingresos] = $this->escenario(expulsado: true);

        $ingresos->method('yaIngreso')->willReturn(false);
        $ingresos->expects(self::never())->method('registrar');

        self::assertSame(
            VeredictoIngreso::Expulsado,
            $accion->ejecutar(2, 7, 30, 'codigo', '20260015'),
        );
    }

    public function test_operador_sin_alcance_es_rechazado(): void
    {
        [$accion, $ingresos] = $this->escenario(autorizado: false);

        $ingresos->expects(self::never())->method('registrar');
        $this->expectException(AccesoPuntoDenegado::class);

        $accion->ejecutar(2, 7, 30, 'codigo', '20260015');
    }

    public function test_aula_ajena_al_examen_es_rechazada(): void
    {
        [$accion, $ingresos] = $this->escenario();

        $ingresos->expects(self::never())->method('registrar');
        $this->expectException(AccesoPuntoDenegado::class);

        $accion->ejecutar(2, 7, 99, 'codigo', '20260015');
    }

    public function test_horario_cerrado_impide_registro(): void
    {
        [$accion, $ingresos] = $this->escenario(ventanaAbierta: false);

        $ingresos->expects(self::never())->method('registrar');
        $this->expectException(DomainException::class);

        $accion->ejecutar(2, 7, 30, 'codigo', '20260015');
    }

    /**
     * @return array{RegistrarIngreso, IngresoGateway&MockObject}
     */
    private function escenario(
        bool $autorizado = true,
        bool $ventanaAbierta = true,
        bool $expulsado = false,
    ): array {
        $estudiante = new EstudianteData(
            15, '20260015', '12345', 'Ana', 'Rojas', true,
        );

        $examen = new ExamenActualData(
            7, 1, 3, 'PRIMER_PARCIAL',
            '2026-10-20', '08:00:00', 120, [12], [30],
        );

        $habilitacion = new HabilitacionData(
            7, 15, true, 30, 'Aula 30',
            null, null, null, null,
        );

        $examenes = $this->createMock(ExamenGateway::class);
        $examenes->method('actual')->willReturn($examen);

        $padron = $this->createMock(PadronGateway::class);
        $padron->method('porCodigos')
            ->willReturn(['20260015' => $estudiante]);

        $inscritos = $this->createMock(InscritosDeExamenGateway::class);
        $inscritos->method('estudiantesDe')->willReturn([$estudiante]);

        $habilitaciones = $this->createMock(HabilitacionGateway::class);
        $habilitaciones->method('condicionDe')
            ->willReturn($habilitacion);

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->method('puedeOperar')->willReturn($autorizado);

        $ventana = $this->createMock(VentanaPuntoControl::class);
        $ventana->method('estaAbierta')->willReturn($ventanaAbierta);

        $expulsiones = $this->createMock(EstadoExpulsionGateway::class);
        $expulsiones->method('estaExpulsado')->willReturn($expulsado);

        $ingresos = $this->createMock(IngresoGateway::class);

        $accion = new RegistrarIngreso(
            $examenes,
            new ConsultarCandidato(
                $padron,
                $inscritos,
                $examenes,
                $habilitaciones,
            ),
            $ingresos,
            $alcance,
            $ventana,
            $expulsiones,
            new EvaluarIngreso,
        );

        return [$accion, $ingresos];
    }
}
