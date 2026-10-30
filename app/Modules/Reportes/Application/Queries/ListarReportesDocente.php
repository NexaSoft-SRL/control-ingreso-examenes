<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Academico\Application\Contracts\PeriodoGateway;
use App\Modules\Reportes\Application\Contracts\ConsultaReportesGateway;
use InvalidArgumentException;

final readonly class ListarReportesDocente
{
    public const POR_PAGINA = 8;

    public function __construct(
        private PeriodoGateway $periodos,
        private ConsultaReportesGateway $reportes,
    ) {}

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array{total: int, pagina: int, por_pagina: int, paginas: int, rendidos: int, periodo: string},
     *     periodos: list<array{id: int, codigo: string}>,
     *     asignaturas: list<array{id: int, codigo: string, nombre: string}>
     * }
     */
    public function execute(
        int $usuarioId,
        ?int $periodoId,
        ?int $asignaturaId,
        int $pagina,
    ): array {
        $vigentes = $this->periodos->vigentes();
        $periodos = [];
        $idsVigentes = [];

        foreach ($vigentes as $periodo) {
            $periodos[] = ['id' => $periodo->id, 'codigo' => $periodo->codigo];
            $idsVigentes[] = $periodo->id;
        }

        if ($periodoId !== null && ! in_array($periodoId, $idsVigentes, true)) {
            throw new InvalidArgumentException('El período seleccionado no está vigente.');
        }

        $idsConsulta = $periodoId === null ? $idsVigentes : [$periodoId];
        $listado = $this->reportes->listar(
            $usuarioId,
            $idsConsulta,
            $periodoId,
            $asignaturaId,
            $pagina,
        );

        return [
            'data' => $listado['data'],
            'meta' => [
                ...$listado['meta'],
                'periodo' => $periodoId === null
                    ? 'Vigentes'
                    : ($periodos[array_search($periodoId, $idsVigentes, true)]['codigo'] ?? 'Vigentes'),
            ],
            'periodos' => $periodos,
            'asignaturas' => $listado['asignaturas'],
        ];
    }
}
