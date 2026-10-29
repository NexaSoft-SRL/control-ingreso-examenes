<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\FiltroRespaldosData;
use App\Modules\Administracion\Application\DTOs\PaginaRespaldosData;
use App\Modules\Administracion\Application\DTOs\RespaldoData;

interface ConsultaRespaldosGateway
{
    public function listar(FiltroRespaldosData $filtro): PaginaRespaldosData;

    public function buscar(int $id): ?RespaldoData;
}
