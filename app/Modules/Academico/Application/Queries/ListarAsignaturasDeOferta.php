<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Application\DTOs\PaginaData;

/**
 * Las asignaturas de la oferta con sus grupos e inscritos, filtradas por
 * periodo, facultad, carrera, nivel y busqueda; y los grupos de una
 * asignatura con su docente, horarios, aula e inscritos.
 */
final readonly class ListarAsignaturasDeOferta
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
    ) {}

    /**
     * @param  array{periodo_ids: list<int>, facultad: string|null, carrera_id: int|null, nivel: string|null, buscar: string|null, sin_lista?: bool}  $filtros
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, pagina: int, por_pagina: int}}
     */
    public function execute(array $filtros, PaginaData $pagina): array
    {
        $resultado = $this->oferta->asignaturasDeOferta($filtros, $pagina);

        return [
            'data' => $resultado['filas'],
            'meta' => [
                ...$pagina->meta($resultado['total']),
                'total_grupos' => $resultado['total_grupos'],
            ],
        ];
    }

    /**
     * @param  array{periodo_ids: list<int>, facultad: string|null, carrera_id: int|null, nivel: string|null, buscar: string|null, sin_lista?: bool}  $filtros
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, pagina: int, por_pagina: int}}
     */
    public function grupos(
        int $asignaturaId,
        array $filtros,
        ?string $buscar,
        PaginaData $pagina,
    ): array {
        $resultado = $this->oferta->gruposDeAsignatura(
            $asignaturaId,
            $filtros['periodo_ids'],
            $buscar,
            $filtros['facultad'],
            $pagina,
        );

        return [
            'data' => $resultado['filas'],
            'meta' => $pagina->meta($resultado['total']),
        ];
    }

    /**
     * Los niveles que hay en lo filtrado, en el orden del plan.
     *
     * @param  array{periodo_ids: list<int>, facultad: string|null, carrera_id: int|null, nivel: string|null, buscar: string|null, sin_lista?: bool}  $filtros
     * @return list<string>
     */
    public function niveles(array $filtros): array
    {
        return $this->oferta->nivelesDeOferta($filtros);
    }

    public function existeAsignatura(int $asignaturaId): bool
    {
        return $this->oferta->existeAsignatura($asignaturaId);
    }
}
