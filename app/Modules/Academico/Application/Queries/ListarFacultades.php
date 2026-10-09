<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;

/**
 * El catalogo de facultades con sus edificios y aulas.
 */
final readonly class ListarFacultades
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
    ) {}

    /**
     * @return list<array{id: int, clave: string, sigla: string, nombre: string, color: string, edificios: int, aulas: int}>
     */
    public function execute(): array
    {
        return $this->oferta->facultades();
    }
}
