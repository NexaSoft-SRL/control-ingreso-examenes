<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;

/**
 * Las incidencias reportadas en un examen. El docente del examen las ve
 * todas; quien atiende un aula, solo las de su aula.
 */
final readonly class ListarIncidencias
{
    public function __construct(
        private IncidenciaGateway $incidencias,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{sin_atender: int, todas: int}}
     */
    public function execute(ExamenData $examen, int $usuarioId, ?int $aulaId, bool $soloSinAtender): array
    {
        if (! $this->alcance->esDocente($usuarioId, $examen->id)) {
            if ($aulaId === null) {
                throw new DatoNoValidoException('aula', 'Indique el aula.');
            }

            if (! $this->alcance->actuaEnExamen(new OperadorData($usuarioId), $examen, $aulaId)) {
                throw FueraDeAlcanceException::aula();
            }
        }

        $lista = $this->incidencias->listar($examen->id, $aulaId, $soloSinAtender);

        return [
            'data' => $lista['data'],
            'meta' => [
                'sin_atender' => $lista['sin_atender'],
                'todas' => $lista['todas'],
            ],
        ];
    }
}
