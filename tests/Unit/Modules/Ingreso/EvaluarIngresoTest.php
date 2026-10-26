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
     * @return array<string, array{bool, ?bool, bool, bool, VeredictoIngreso}>
     */
    public static function escenarios(): array
    {
        return [
            'admitido' => [true, true, false, false, VeredictoIngreso::Admitido],
            'inhabilitado' => [true, false, false, false, VeredictoIngreso::NoHabilitado],
            'sin condicion registrada' => [true, null, false, false, VeredictoIngreso::NoHabilitado],
            'ingreso previo' => [true, true, true, false, VeredictoIngreso::YaIngreso],
            'no inscrito' => [false, true, false, false, VeredictoIngreso::NoCorresponde],
            'expulsado' => [true, true, true, true, VeredictoIngreso::Expulsado],
            'no inscrito aunque tenga otras condiciones' => [false, true, true, true, VeredictoIngreso::NoCorresponde],
        ];
    }

    #[DataProvider('escenarios')]
    public function test_evalua_las_reglas(
        bool $inscrito,
        ?bool $habilitado,
        bool $yaIngreso,
        bool $expulsado,
        VeredictoIngreso $esperado,
    ): void {
        $resultado = (new EvaluarIngreso)->evaluar($inscrito, $habilitado, $yaIngreso, $expulsado);

        self::assertSame($esperado, $resultado);
        self::assertSame(
            $esperado === VeredictoIngreso::Admitido,
            $resultado->permiteIngreso(),
        );
    }

    public function test_el_aula_del_reparto_es_una_sugerencia(): void
    {
        // Habilitado en otra aula o sin aula: la puerta lo admite igual.
        self::assertSame(VeredictoIngreso::Admitido, (new EvaluarIngreso)->evaluar(true, true, false, false));
    }

    public function test_entre_varios_examenes_prefiere_el_que_lo_admite_en_esta_aula(): void
    {
        $reglas = new EvaluarIngreso;

        self::assertSame(0, $reglas->puesto(false, true, 10, 10));
        self::assertSame(1, $reglas->puesto(false, true, 20, 10));
        self::assertSame(1, $reglas->puesto(false, true, null, 10));
        self::assertSame(2, $reglas->puesto(false, false, 10, 10));
        self::assertSame(2, $reglas->puesto(false, null, null, 10));
        self::assertSame(3, $reglas->puesto(true, true, 10, 10));
    }

    public function test_tarde_pasados_quince_minutos_del_inicio(): void
    {
        $reglas = new EvaluarIngreso;

        self::assertNull($reglas->minutosTarde(-30));
        self::assertNull($reglas->minutosTarde(15));
        self::assertSame(16, $reglas->minutosTarde(16));
    }

    public function test_relectura_en_la_misma_puerta_dentro_de_dos_minutos(): void
    {
        $reglas = new EvaluarIngreso;

        self::assertTrue($reglas->esRelectura(10, 10, 30));
        self::assertTrue($reglas->esRelectura(10, 10, 120));
        self::assertFalse($reglas->esRelectura(10, 10, 121));
        self::assertFalse($reglas->esRelectura(20, 10, 5));
        self::assertFalse($reglas->esRelectura(null, 10, 5));
    }
}
