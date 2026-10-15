<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Rules;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class ComprobarVentanaIngreso
{
    public function comprobar(
        string $fecha,
        string $horaInicio,
        int $duracionMinutos,
        DateTimeImmutable $ahora,
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

        $fin = $inicio->modify("+{$duracionMinutos} minutes");

        return $ahora >= $inicio && $ahora < $fin;
    }
}
