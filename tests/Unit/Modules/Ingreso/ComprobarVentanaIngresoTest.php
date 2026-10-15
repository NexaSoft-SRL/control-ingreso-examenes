<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Ingreso;

use App\Modules\Ingreso\Domain\Rules\ComprobarVentanaIngreso;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ComprobarVentanaIngresoTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function horarios(): array
    {
        return [
            'un segundo antes' => ['2026-10-20 07:59:59', false],
            'inicio exacto' => ['2026-10-20 08:00:00', true],
            'durante el examen' => ['2026-10-20 09:00:00', true],
            'ultimo segundo' => ['2026-10-20 09:59:59', true],
            'fin exacto' => ['2026-10-20 10:00:00', false],
            'dia siguiente' => ['2026-10-21 08:00:00', false],
        ];
    }

    #[DataProvider('horarios')]
    public function test_evalua_limites_temporales(
        string $momento,
        bool $esperado,
    ): void {
        $zona = new DateTimeZone('America/La_Paz');
        $ahora = new DateTimeImmutable($momento, $zona);

        self::assertSame(
            $esperado,
            (new ComprobarVentanaIngreso)->comprobar(
                '2026-10-20',
                '08:00:00',
                120,
                $ahora,
            ),
        );
    }
}
