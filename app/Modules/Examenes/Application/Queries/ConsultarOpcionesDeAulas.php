<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\DTOs\OpcionesDeAulasData;

/**
 * Paso 3 del asistente: las aulas que sugieren los horarios de los grupos,
 * las clases del horario a la hora del examen y los examenes
 * que se solapan. Todo es aviso: el docente elige igual.
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
        $conHora = $fecha !== null && $horaInicio !== null && $duracionMinutos !== null;
        $examenes = $conHora
            ? $this->consulta->examenesALaHora($grupoIds, $fecha, $horaInicio, $duracionMinutos, $exceptoExamenId)
            : [];

        return new OpcionesDeAulasData(
            sugeridas: $grupoIds === [] ? [] : $this->consulta->aulasSugeridas($grupoIds),
            clases: $conHora ? $this->consulta->clasesALaHora($grupoIds, $fecha, $horaInicio, $duracionMinutos) : [],
            examenes: $examenes,
            compartidas: array_map(
                fn (array $lista): array => array_map(fn (array $examen): string => $examen['examen'], $lista),
                $examenes,
            ),
        );
    }
}
