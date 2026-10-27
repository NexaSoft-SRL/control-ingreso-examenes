<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Quien dejo la incidencia: alguien que la reporto o la puerta al rechazar
 * un intento. Las del sistema no cuentan como «sin atender».
 */
enum OrigenIncidencia: string
{
    case Persona = 'PERSONA';
    case Sistema = 'SISTEMA';

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
            self::Persona => 'Persona',
            self::Sistema => 'Sistema',
        };
    }
}
