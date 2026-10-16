<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use App\Modules\Ingreso\Application\DTOs\ResultadoResolucionPuertaData;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class IdentificarEnPuerta
{
    public function __construct(
        private ExamenesDePuerta $examenes,
        private ResolverIngresoAutorizadoEnPuerta $resolver,
    ) {}

    public function ejecutar(
        int $usuarioId,
        int $aulaId,
        string $tipo,
        string $identificador,
        DateTimeImmutable $ahora,
    ): ResultadoResolucionPuertaData {
        if ($usuarioId <= 0 || $aulaId <= 0) {
            throw new InvalidArgumentException(
                'Operador o aula inválidos.'
            );
        }

        $fecha = $ahora
            ->setTimezone(new DateTimeZone('America/La_Paz'))
            ->format('Y-m-d');

        $examenes = $this->examenes->examenesDelAula(
            $aulaId,
            $fecha,
        );

        return $this->resolver->ejecutar(
            $usuarioId,
            $aulaId,
            $examenes,
            $tipo,
            $identificador,
        );
    }
}
