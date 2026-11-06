<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Rules;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * La puerta abre una hora antes del inicio. La duracion es la prevista: el
 * examen sigue abierto hasta que un docente lo finaliza (`$finalizadoEn`,
 * `AAAA-MM-DD HH:MM:SS`) o, si nadie lo hace, hasta que termina su dia.
 */
final readonly class ComprobarVentanaIngreso
{
    public function comprobar(
        string $fecha,
        string $horaInicio,
        int $duracionMinutos,
        DateTimeImmutable $ahora,
        ?string $finalizadoEn = null,
    ): bool {
        if ($duracionMinutos <= 0) {
            throw new InvalidArgumentException('Duración inválida.');
        }

        $zona = new DateTimeZone('America/La_Paz');

        $formatoHora = match (true) {
            preg_match('/^\\d{2}:\\d{2}$/D', $horaInicio) === 1 => 'H:i',
            preg_match('/^\\d{2}:\\d{2}:\\d{2}$/D', $horaInicio) === 1 => 'H:i:s',
            default => null,
        };

        if ($formatoHora === null) {
            throw new InvalidArgumentException('Fecha u hora inválida.');
        }

        $inicio = DateTimeImmutable::createFromFormat(
            '!Y-m-d '.$formatoHora,
            $fecha.' '.$horaInicio,
            $zona,
        );

        if (
            $inicio === false
            || DateTimeImmutable::getLastErrors() !== false
        ) {
            throw new InvalidArgumentException('Fecha u hora inválida.');
        }

        $cierre = $finalizadoEn !== null
            ? new DateTimeImmutable($finalizadoEn, $zona)
            : $inicio->setTime(0, 0)->modify('+1 day');

        $apertura = $inicio->modify('-60 minutes');

        return $ahora >= $apertura && $ahora < $cierre;
    }
}
