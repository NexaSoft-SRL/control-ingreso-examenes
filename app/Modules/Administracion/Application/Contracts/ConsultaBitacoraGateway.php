<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\BitacoraOperacionData;
use App\Modules\Administracion\Application\DTOs\ConsultarBitacoraData;

interface ConsultaBitacoraGateway
{
    /**
     * @return list<BitacoraOperacionData>
     */
    public function consultar(
        ConsultarBitacoraData $filtros,
    ): array;
}
