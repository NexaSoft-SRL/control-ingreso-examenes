<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuerta;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Domain\Exceptions\IncidenciaYaAtendidaException;

/**
 * Cualquier docente del examen atiende una incidencia. No se deshace.
 */
final readonly class AtenderIncidencia
{
    public function __construct(
        private IncidenciaGateway $incidencias,
        private AlcancePuerta $alcance,
    ) {}

    public function ejecutar(int $incidenciaId, int $examenId, int $usuarioId): void
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        if (! $this->incidencias->atender($incidenciaId, $usuarioId)) {
            throw new IncidenciaYaAtendidaException;
        }
    }
}
