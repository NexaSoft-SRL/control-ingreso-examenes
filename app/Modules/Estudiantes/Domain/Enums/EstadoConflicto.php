<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Enums;

/**
 * Un conflicto se resuelve una sola vez.
 */
enum EstadoConflicto: string
{
    case Pendiente = 'PENDIENTE';
    case Resuelto = 'RESUELTO';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $estado): string => $estado->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Resuelto => 'Resuelto',
        };
    }
}
