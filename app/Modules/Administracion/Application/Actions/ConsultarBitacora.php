<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\ConsultaBitacoraGateway;
use App\Modules\Administracion\Application\DTOs\BitacoraOperacionData;
use App\Modules\Administracion\Application\DTOs\ConsultarBitacoraData;

final readonly class ConsultarBitacora
{
    public function __construct(
        private ConsultaBitacoraGateway $gateway,
    ) {}

    /**
     * @return list<BitacoraOperacionData>
     */
    public function execute(
        ConsultarBitacoraData $filtros,
    ): array {
        return $this->gateway->consultar($filtros);
    }
}
