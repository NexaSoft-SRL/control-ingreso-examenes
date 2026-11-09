<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\ConsultarBitacoraData;
use App\Modules\Administracion\Application\DTOs\PaginaBitacoraData;

interface ConsultaBitacoraGateway
{
    /**
     * Una pagina de asientos, del mas reciente al mas antiguo.
     */
    public function consultar(ConsultarBitacoraData $filtros): PaginaBitacoraData;
}
