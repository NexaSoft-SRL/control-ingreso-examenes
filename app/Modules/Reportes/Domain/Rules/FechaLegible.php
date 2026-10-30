<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Rules;

/**
 * Las fechas como se leen en un reporte: '12 oct 2026' y
 * '12 oct 2026, 08:21'. Lo que no es una fecha queda como vino.
 */
final class FechaLegible
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    // '2026-10-12' (o el comienzo de un instante) → '12 oct 2026'.
    public static function dia(string $fecha): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $partes) !== 1) {
            return $fecha;
        }

        return sprintf('%d %s %s', (int) $partes[3], self::MESES[(int) $partes[2] - 1] ?? $partes[2], $partes[1]);
    }

    // '2026-10-12 08:21:07' → '12 oct 2026, 08:21'.
    public static function instante(string $instante): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T](\d{2}:\d{2})/', $instante, $partes) !== 1) {
            return self::dia($instante);
        }

        return self::dia($instante).', '.$partes[1];
    }
}
