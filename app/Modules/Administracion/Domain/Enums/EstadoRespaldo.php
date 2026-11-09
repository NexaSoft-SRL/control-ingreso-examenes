<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Enums;

/**
 * Un respaldo fallido tambien deja fila, con su error.
 */
enum EstadoRespaldo: string
{
    case Correcto = 'CORRECTO';
    case Fallido = 'FALLIDO';

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
            self::Correcto => 'Correcto',
            self::Fallido => 'Fallido',
        };
    }
}
