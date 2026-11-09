<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Que tan grave es una incidencia: leve, grave o muy grave. Sale del tipo
 * (no se guarda) y se muestra con color: rojo la muy grave, ambar la grave
 * y azul la leve.
 */
enum Gravedad: string
{
    case MuyGrave = 'MUY_GRAVE';
    case Grave = 'GRAVE';
    case Leve = 'LEVE';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(
            static fn (self $gravedad): string => $gravedad->value,
            self::cases(),
        );
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::MuyGrave => 'Muy grave',
            self::Grave => 'Grave',
            self::Leve => 'Leve',
        };
    }
}
