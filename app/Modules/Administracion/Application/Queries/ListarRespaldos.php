<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Queries;

use App\Modules\Administracion\Application\Contracts\ConsultaRespaldosGateway;
use App\Modules\Administracion\Application\DTOs\FiltroRespaldosData;
use App\Modules\Administracion\Application\DTOs\PaginaRespaldosData;

final readonly class ListarRespaldos
{
    public function __construct(
        private ConsultaRespaldosGateway $respaldos,
    ) {}

    public function execute(FiltroRespaldosData $filtro): PaginaRespaldosData
    {
        return $this->respaldos->listar($filtro);
    }
}
