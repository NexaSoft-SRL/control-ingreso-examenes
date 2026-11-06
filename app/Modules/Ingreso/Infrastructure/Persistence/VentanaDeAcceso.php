<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use DateTimeImmutable;

/**
 * La ventana en que un codigo de aula abre la puerta: desde una hora antes
 * del inicio hasta que un docente finaliza el examen o, si nadie lo hace,
 * hasta que termina su dia, en la zona de la aplicacion.
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

    /**
     * El termino que se muestra: el real si ya se finalizo; si no, el
     * previsto por la duracion.
     */
    public static function fin(string $fecha, string $horaInicio, int $duracionMinutos, ?string $finalizadoEn = null): DateTimeImmutable
    {
        return $finalizadoEn !== null
            ? new DateTimeImmutable($finalizadoEn, now()->getTimezone())
            : self::inicio($fecha, $horaInicio)->modify(sprintf('+%d minutes', $duracionMinutos));
    }

    /**
     * Cuando deja de valer: al finalizarse el examen o, sin finalizar, al
     * terminar su dia.
     */
    public static function cierre(string $fecha, ?string $finalizadoEn): DateTimeImmutable
    {
        return $finalizadoEn !== null
            ? new DateTimeImmutable($finalizadoEn, now()->getTimezone())
            : self::inicio($fecha, '00:00')->modify('+1 day');
    }
}
