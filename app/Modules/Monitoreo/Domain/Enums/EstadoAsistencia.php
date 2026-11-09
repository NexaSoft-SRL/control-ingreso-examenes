<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Domain\Enums;

/**
 * En que situacion esta cada estudiante habilitado mientras corre el
 * examen (HU-28).
 */
enum EstadoAsistencia: string
{
    case Ingresado = 'INGRESADO';
    case NoPresentado = 'NO_PRESENTADO';
    case Expulsado = 'EXPULSADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ingresado => 'Ingresado',
            self::NoPresentado => 'No presentado',
            self::Expulsado => 'Expulsado',
        };
    }
}
