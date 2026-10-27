<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Examenes;

use App\Modules\Examenes\Domain\Rules\AvanceDeExamen;
use PHPUnit\Framework\TestCase;

final class AvanceDeExamenTest extends TestCase
{
    public function test_nadie_atiende_mas_de_dos_aulas_a_la_vez(): void
    {
        $dos = AvanceDeExamen::calcular(3, 10, 0, 10, 2);
        self::assertSame('Listo', $dos['estado']);

        $tres = AvanceDeExamen::calcular(3, 10, 0, 10, 3);
        self::assertSame('Falta quién atiende', $tres['estado']);
        self::assertSame('quien_atiende', $tres['accion']);
        self::assertSame(3, $tres['paso']);
    }
}
