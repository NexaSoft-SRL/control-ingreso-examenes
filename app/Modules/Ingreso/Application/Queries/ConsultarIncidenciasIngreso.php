<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\IncidenciasIngresoGateway;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use InvalidArgumentException;

final readonly class ConsultarIncidenciasIngreso
{
    public function __construct(
        private AlcancePuntoControl $alcance,
        private IncidenciasIngresoGateway $incidencias,
    ) {}

    public function ejecutar(
        int $usuarioId,
        int $examenId,
        int $aulaId,
        int $limite = 20,
    ): array {
        if (
            $usuarioId <= 0
            || $examenId <= 0
            || $aulaId <= 0
            || $limite < 1
            || $limite > 50
        ) {
            throw new InvalidArgumentException(
                'Parámetros de consulta inválidos.',
            );
        }

        if (! $this->alcance->puedeOperar(
            $usuarioId,
            $examenId,
            $aulaId,
        )) {
            throw new AccesoPuntoDenegado(
                'El operador no está autorizado para este punto.',
            );
        }

        return $this->incidencias->recientes(
            $examenId,
            $aulaId,
            $limite,
        );
    }
}
