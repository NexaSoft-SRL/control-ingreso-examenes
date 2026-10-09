<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Academico\Application\Contracts\PeriodoGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\DTOs\OpcionesDeGruposData;

/**
 * Paso 2 del asistente: los grupos de la asignatura en los periodos
 * vigentes, los del docente por un lado y los de los demas por otro.
 */
final readonly class ConsultarOpcionesDeGrupos
{
    public function __construct(
        private ConsultaExamenGateway $consulta,
        private PeriodoGateway $periodos,
    ) {}

    public function execute(int $asignaturaId, int $usuarioId): OpcionesDeGruposData
    {
        $periodoIds = [];

        foreach ($this->periodos->vigentes() as $vigente) {
            $periodoIds[] = $vigente->id;
        }

        $propios = [];
        $otros = [];

        foreach ($this->consulta->gruposDeAsignatura($asignaturaId, $periodoIds, $usuarioId) as $grupo) {
            if ($grupo->propio) {
                $propios[] = $grupo;
            } else {
                $otros[] = $grupo;
            }
        }

        return new OpcionesDeGruposData($propios, $otros);
    }
}
