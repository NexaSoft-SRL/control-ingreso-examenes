<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Enums;

/**
 * Una carrera anual tiene sus grupos en el periodo `0/AAAA`.
 */
enum RegimenCarrera: string
{
    case Semestral = 'SEMESTRAL';
    case Anual = 'ANUAL';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $regimen): string => $regimen->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Semestral => 'Semestral',
            self::Anual => 'Anual',
        };
    }
}
