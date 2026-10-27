<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Que tan grave es una incidencia. Sale del tipo y se muestra con color:
 * rojo la grave, ambar la media y azul la leve.
 */
enum Gravedad: string
{
    case Grave = 'GRAVE';
    case Media = 'MEDIA';
    case Leve = 'LEVE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Grave => 'Grave',
            self::Media => 'Media',
            self::Leve => 'Leve',
        };
    }
}
