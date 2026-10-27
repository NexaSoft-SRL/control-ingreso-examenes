<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Authorization\AlcancePuerta;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;

/**
 * Las incidencias de un examen. El docente del examen las ve todas; quien
 * atiende aulas, solo las de sus aulas.
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
        // El docente ve todas; quien atiende aulas, solo las suyas.
        $aulas = $aulaId === null ? null : [$aulaId];

        if (! $this->alcance->esDocente($usuarioId, $examenId)) {
            $suyas = $this->alcance->aulasQueAtiende($usuarioId, $examenId);
            $aulas = $aulaId === null ? $suyas : array_values(array_intersect([$aulaId], $suyas));

            if ($aulas === []) {
                throw new AccesoPuntoDenegado('Sin asignación en esta aula.');
            }
        }

        $lista = $this->incidencias->listar($examenId, $aulas, $soloSinAtender);

        return [
            'data' => $lista['data'],
            'meta' => [
                'sin_atender' => $lista['sin_atender'],
                'todas' => $lista['todas'],
            ],
        ];
    }
}
