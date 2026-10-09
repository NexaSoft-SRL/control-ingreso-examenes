<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Enums;

/**
 * Los periodos que maneja la universidad. El numero del codigo (`2/2026`)
 * dice cual es: 0 anual, 1 y 2 semestres, 3 verano, 4 invierno.
 */
enum TipoPeriodo: string
{
    case Semestre1 = 'SEMESTRE_1';
    case Semestre2 = 'SEMESTRE_2';
    case Anual = 'ANUAL';
    case Invierno = 'INVIERNO';
    case Verano = 'VERANO';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $tipo): string => $tipo->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Semestre1 => 'Semestre 1',
            self::Semestre2 => 'Semestre 2',
            self::Anual => 'Anual',
            self::Invierno => 'Invierno',
            self::Verano => 'Verano',
        };
    }
}
