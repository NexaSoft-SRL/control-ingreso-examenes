<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Estudiantes\Application\Contracts\ConflictoGateway;
use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;

final readonly class ListarConflictos
{
    public function __construct(
        private ConflictoGateway $conflictos,
    ) {}

    public function execute(FiltroListaData $filtro): PaginaData
    {
        return $this->conflictos->listar($filtro);
    }
}
