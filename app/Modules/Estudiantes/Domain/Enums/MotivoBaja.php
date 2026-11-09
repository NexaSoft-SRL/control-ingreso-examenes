<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Enums;

/**
 * Por que el docente quita a un inscrito de su grupo. `Otro` exige un
 * detalle escrito.
 */
enum MotivoBaja: string
{
    case RetiroMateria = 'RETIRO_MATERIA';
    case CargadoPorError = 'CARGADO_POR_ERROR';
    case Otro = 'OTRO';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $motivo): string => $motivo->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::RetiroMateria => 'Retiro de la materia',
            self::CargadoPorError => 'Cargado por error',
            self::Otro => 'Otro',
        };
    }
}
