<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\PeriodoGateway;

/**
 * Los periodos vigentes hoy y el principal (el que tiene mas grupos).
 */
final readonly class ConsultarPeriodosVigentes
{
    public function __construct(
        private PeriodoGateway $periodos,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, principal: string|null}
     */
    public function execute(): array
    {
        $vigentes = [];

        foreach ($this->periodos->vigentes() as $periodo) {
            $vigentes[] = [
                'id' => $periodo->id,
                'codigo' => $periodo->codigo,
                'tipo' => $periodo->tipoEtiqueta,
                'fecha_inicio' => $periodo->fechaInicio,
                'fecha_fin' => $periodo->fechaFin,
                'ventanas' => (object) $periodo->ventanas,
            ];
        }

        return [
            'data' => $vigentes,
            'principal' => $this->periodos->principal()?->codigo,
        ];
    }
}
