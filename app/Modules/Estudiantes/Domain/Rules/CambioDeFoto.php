<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Rules;

use DateTimeImmutable;

/**
 * Cuando se puede volver a subir la foto. El estudiante la sube una vez en
 * el plazo general; despues, un docente de sus grupos puede habilitarle un
 * cambio por unos dias, pero no antes de un año desde la ultima foto: asi
 * un suplantador no la cambia apenas pasa el examen.
 */
final class CambioDeFoto
{
    public const MESES_DE_BLOQUEO = 12;

    public const DIAS_HABILITADO = 7;

    /**
     * El primer dia (`AAAA-MM-DD`) en que se puede habilitar otro cambio,
     * o nulo si no tiene foto.
     */
    public static function habilitableDesde(?string $fotoSubidaEn): ?string
    {
        if ($fotoSubidaEn === null || $fotoSubidaEn === '') {
            return null;
        }

        return (new DateTimeImmutable(substr($fotoSubidaEn, 0, 10)))
            ->modify(sprintf('+%d months', self::MESES_DE_BLOQUEO))
            ->format('Y-m-d');
    }

    /**
     * Hasta que dia vale una habilitacion dada hoy.
     */
    public static function habilitadaHasta(string $hoy): string
    {
        return (new DateTimeImmutable($hoy))
            ->modify(sprintf('+%d days', self::DIAS_HABILITADO))
            ->format('Y-m-d');
    }
}
