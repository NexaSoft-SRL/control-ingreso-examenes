<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Enums;

/**
 * Quien pidio el respaldo: el planificador cada noche o una persona desde
 * la pantalla.
 */
enum TipoRespaldo: string
{
    case Programado = 'PROGRAMADO';
    case Puntual = 'PUNTUAL';

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
            self::Programado => 'Programado',
            self::Puntual => 'Puntual',
        };
    }
}
