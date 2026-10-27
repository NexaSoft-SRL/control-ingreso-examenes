<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use DateTimeImmutable;

/**
 * La ventana en que un codigo de aula abre la puerta: desde una hora antes
 * del inicio hasta el final del examen, en la zona de la aplicacion.
 */
final class VentanaDeAcceso
{
    public const MINUTOS_ANTES = 60;

    public static function inicio(string $fecha, string $horaInicio): DateTimeImmutable
    {
        return new DateTimeImmutable(
            substr($fecha, 0, 10).' '.substr($horaInicio, 0, 5).':00',
            now()->getTimezone(),
        );
    }

    public static function abre(string $fecha, string $horaInicio): DateTimeImmutable
    {
        return self::inicio($fecha, $horaInicio)->modify(sprintf('-%d minutes', self::MINUTOS_ANTES));
    }

    public static function fin(string $fecha, string $horaInicio, int $duracionMinutos): DateTimeImmutable
    {
        return self::inicio($fecha, $horaInicio)->modify(sprintf('+%d minutes', $duracionMinutos));
    }
}
