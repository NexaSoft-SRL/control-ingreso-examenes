<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Application\DTOs\PaginaData;

/**
 * Los grupos de una asignatura con docente, horarios e inscritos.
 */
final readonly class ListarGruposDeAsignatura
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
        private ResolverPeriodos $resolverPeriodos,
    ) {}

    /**
     * Devuelve null si la asignatura no existe.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, pagina: int, por_pagina: int}}|null
     */
    public function execute(
        int $asignaturaId,
        ?int $periodoId,
        ?string $claveFacultad,
        ?string $buscar,
        PaginaData $pagina,
    ): ?array {
        if (! $this->oferta->existeAsignatura($asignaturaId)) {
            return null;
        }

        // Una asignatura tiene pocos grupos: se ordenan enteros y se corta
        // la pagina aqui.
        $grupos = $this->oferta->gruposDeAsignatura(
            $asignaturaId,
            $this->resolverPeriodos->execute($periodoId),
            $claveFacultad,
            $buscar,
        );

        return [
            'data' => array_slice(
                $grupos,
                ($pagina->pagina - 1) * $pagina->porPagina,
                $pagina->porPagina,
            ),
            'meta' => $pagina->meta(count($grupos)),
        ];
    }
}
