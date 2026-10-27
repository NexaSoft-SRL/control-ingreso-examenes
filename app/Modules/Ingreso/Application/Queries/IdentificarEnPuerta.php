<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use App\Modules\Ingreso\Application\DTOs\ResultadoResolucionPuertaData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Exceptions\CodigoQrNoValido;
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
            try {
                [$examenId, $codigo] = $this->resolverQr->ejecutar(
                    $usuarioId,
                    $aulaId,
                    $examenes,
                    $identificador,
                );
            } catch (CodigoQrNoValido) {
                return new ResultadoResolucionPuertaData(
                    null,
                    null,
                    causa: CausaIntento::QrNoValido,
                );
            }

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
                $codigo,
                $resultado->candidato,
                $resultado->causa,
                $resultado->estudiante,
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
