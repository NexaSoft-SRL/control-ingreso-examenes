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
        private ResolverQrEnPuerta $resolverQr,
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

        if ($tipo === 'qr') {
            [$examenId, $codigo] = $this->resolverQr->ejecutar(
                $usuarioId,
                $aulaId,
                $examenes,
                $identificador,
            );

            $resultado = $this->resolver->ejecutar(
                $usuarioId,
                $aulaId,
                [$examenId],
                'codigo',
                $codigo,
            );

            return new ResultadoResolucionPuertaData(
                $resultado->examenId,
                $resultado->veredicto,
                $resultado->ambiguo,
                $codigo,
                $resultado->candidato,
            );
        }

        return $this->resolver->ejecutar(
            $usuarioId,
            $aulaId,
            $examenes,
            $tipo,
            $identificador,
        );
    }
}
