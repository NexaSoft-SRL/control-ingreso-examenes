<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Que tan grave es una incidencia, en los tres niveles que pidio la
 * consultora en la decimotercera revision. Sale del tipo y se muestra con
 * color: rojo la muy grave, ambar la grave y azul la leve.
 */
enum Gravedad: string
{
    case MuyGrave = 'MUY_GRAVE';
    case Grave = 'GRAVE';
    case Leve = 'LEVE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MuyGrave => 'Muy grave',
            self::Grave => 'Grave',
            self::Leve => 'Leve',
        };
    }
}
