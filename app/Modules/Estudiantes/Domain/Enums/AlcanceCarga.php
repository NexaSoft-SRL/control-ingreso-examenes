<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Enums;

/**
 * Una carga trae los inscritos de un grupo o las inscripciones de toda
 * una facultad.
 */
enum AlcanceCarga: string
{
    case Grupo = 'GRUPO';
    case Facultad = 'FACULTAD';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $alcance): string => $alcance->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Grupo => 'Grupo',
            self::Facultad => 'Facultad',
        };
    }
}
