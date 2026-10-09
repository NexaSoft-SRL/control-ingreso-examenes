<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;

final readonly class ListarEstudiantes
{
    public function __construct(
        private PadronGateway $padron,
    ) {}

    public function execute(FiltroListaData $filtro): PaginaData
    {
        return $this->padron->listar($filtro);
    }
}
