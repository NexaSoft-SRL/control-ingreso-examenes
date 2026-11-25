<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Queries;

use App\Modules\Administracion\Application\Contracts\AjustesDeRespaldoGateway;
use App\Modules\Administracion\Application\DTOs\AjustesDeRespaldoData;

final readonly class ConsultarAjustesDeRespaldo
{
    public function __construct(
        private AjustesDeRespaldoGateway $ajustes,
    ) {}

    public function execute(): AjustesDeRespaldoData
    {
        return $this->ajustes->leer();
    }
}
