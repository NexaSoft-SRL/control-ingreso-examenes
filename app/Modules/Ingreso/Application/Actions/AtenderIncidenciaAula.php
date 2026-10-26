<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Contracts\SeguimientoAulaGateway;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use InvalidArgumentException;

final readonly class AtenderIncidenciaAula
{
    public function __construct(
        private SeguimientoAulaGateway $seguimiento,
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

        // Como en «En curso»: el docente del examen o el personal de esa aula.
        if (! $this->seguimiento->tieneAlcance($usuarioId, $ubicacion['examen_id'], $ubicacion['aula_id'])) {
            throw new AccesoPuntoDenegado('Solo atiende las incidencias de sus aulas.');
        }

        return $this->seguimiento->marcarIncidenciaAtendida($incidenciaId, $usuarioId);
    }
}
