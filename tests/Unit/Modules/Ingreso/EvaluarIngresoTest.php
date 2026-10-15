<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ingreso;

use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use App\Modules\Ingreso\Domain\Rules\EvaluarIngreso;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EvaluarIngresoTest extends TestCase
{
    /**
     * @return array<string, array{
     *     bool, ?bool, ?int, int, bool, bool, VeredictoIngreso
     * }>
     */
    public static function escenarios(): array
    {
        return [
            'admitido' => [
                true, true, 10, 10, false, false,
                VeredictoIngreso::Admitido,
            ],
            'inhabilitado' => [
                true, false, 10, 10, false, false,
                VeredictoIngreso::NoHabilitado,
            ],
            'sin condicion registrada' => [
                true, null, null, 10, false, false,
                VeredictoIngreso::NoHabilitado,
            ],
            'aula diferente' => [
                true, true, 20, 10, false, false,
                VeredictoIngreso::OtraAula,
            ],
            'ingreso previo' => [
                true, true, 10, 10, true, false,
                VeredictoIngreso::YaIngreso,
            ],
            'no inscrito' => [
                false, true, 10, 10, false, false,
                VeredictoIngreso::NoCorresponde,
            ],
            'sin aula asignada' => [
                true, true, null, 10, false, false,
                VeredictoIngreso::NoCorresponde,
            ],
            'expulsado' => [
                true, true, 10, 10, true, true,
                VeredictoIngreso::Expulsado,
            ],
            'no inscrito aunque tenga otras condiciones' => [
                false, true, 10, 10, true, true,
                VeredictoIngreso::NoCorresponde,
            ],
        ];
    }

    #[DataProvider('escenarios')]
    public function test_evalua_las_reglas(
        bool $inscrito,
        ?bool $habilitado,
        ?int $aulaAsignada,
        int $aulaControl,
        bool $yaIngreso,
        bool $expulsado,
        VeredictoIngreso $esperado,
    ): void {
        $resultado = (new EvaluarIngreso)->evaluar(
            $inscrito,
            $habilitado,
            $aulaAsignada,
            $aulaControl,
            $yaIngreso,
            $expulsado,
        );

        self::assertSame($esperado, $resultado);
        self::assertSame(
            $esperado === VeredictoIngreso::Admitido,
            $resultado->permiteIngreso(),
        );
    }
}
