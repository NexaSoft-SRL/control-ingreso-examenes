<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Enums;

/**
 * Quien trajo al estudiante o su inscripcion: una carga de administracion
 * o la lista que sube un docente para su grupo. Es tambien la via de una
 * inscripcion y de un conflicto.
 */
enum OrigenEstudiante: string
{
    case Administracion = 'ADMINISTRACION';
    case Docente = 'DOCENTE';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $origen): string => $origen->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administracion => 'Administración',
            self::Docente => 'Docente',
        };
    }
}
