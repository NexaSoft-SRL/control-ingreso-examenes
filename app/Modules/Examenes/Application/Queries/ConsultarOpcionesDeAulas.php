<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\DTOs\OpcionesDeAulasData;

/**
 * Paso 3 del asistente: las aulas que sugieren los horarios de los grupos
 * y el aviso de aula compartida. Compartir aula no se impide.
 */
final readonly class ConsultarOpcionesDeAulas
{
    public function __construct(
        private ConsultaExamenGateway $consulta,
    ) {}

    /**
     * @param  list<int>  $grupoIds
     */
    public function execute(
        array $grupoIds,
        ?string $fecha,
        ?string $horaInicio,
        ?int $duracionMinutos,
        ?int $exceptoExamenId,
    ): OpcionesDeAulasData {
        $compartidas = $fecha !== null && $horaInicio !== null && $duracionMinutos !== null
            ? $this->consulta->aulasCompartidas($fecha, $horaInicio, $duracionMinutos, $exceptoExamenId)
            : [];

        return new OpcionesDeAulasData(
            sugeridas: $grupoIds === [] ? [] : $this->consulta->aulasSugeridas($grupoIds),
            compartidas: $compartidas,
        );
    }
}
