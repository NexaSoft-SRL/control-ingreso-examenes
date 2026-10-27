<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Authorization\AlcancePuerta;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;

/**
 * Las incidencias de un examen. El docente del examen las ve todas; quien
 * atiende un aula, solo las de su aula.
 */
final readonly class ListarIncidencias
{
    public function __construct(
        private IncidenciaGateway $incidencias,
        private AlcancePuerta $alcance,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{sin_atender: int, todas: int}}
     */
    public function ejecutar(
        int $examenId,
        int $usuarioId,
        ?int $aulaId,
        bool $soloSinAtender,
    ): array {
        if (! $this->alcance->esDocente($usuarioId, $examenId)) {
            if ($aulaId === null) {
                throw new DatoNoValidoException('aula', 'Indique el aula.');
            }

            if (! $this->alcance->atiendeAula($usuarioId, $examenId, $aulaId)) {
                throw new AccesoPuntoDenegado('Sin asignación en esta aula.');
            }
        }

        $lista = $this->incidencias->listar($examenId, $aulaId, $soloSinAtender);

        return [
            'data' => $lista['data'],
            'meta' => [
                'sin_atender' => $lista['sin_atender'],
                'todas' => $lista['todas'],
            ],
        ];
    }
}
