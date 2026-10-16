<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Ingreso\Application\Contracts\SeguimientoAulaGateway;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use InvalidArgumentException;

final readonly class AtenderIncidenciaAula
{
    public function __construct(
        private SeguimientoAulaGateway $seguimiento,
        private AlcanceExamenGateway $alcanceExamen,
    ) {}

    public function ejecutar(int $usuarioId, int $incidenciaId): bool
    {
        if ($usuarioId <= 0 || $incidenciaId <= 0) {
            throw new InvalidArgumentException('Identificadores inválidos.');
        }

        $ubicacion = $this->seguimiento->ubicacionIncidencia($incidenciaId);

        if ($ubicacion === null) {
            throw new InvalidArgumentException('Incidencia no encontrada.');
        }

        if (! $this->alcanceExamen->esDocenteDelExamen($usuarioId, $ubicacion['examen_id'])) {
            throw new AccesoPuntoDenegado('Solo el docente del examen puede atender esta incidencia.');
        }

        return $this->seguimiento->marcarIncidenciaAtendida($incidenciaId, $usuarioId);
    }
}
