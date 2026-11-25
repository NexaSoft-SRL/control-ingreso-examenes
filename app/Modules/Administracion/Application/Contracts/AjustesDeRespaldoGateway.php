<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\AjustesDeRespaldoData;

interface AjustesDeRespaldoGateway
{
    /**
     * Los ajustes vigentes: los guardados desde la pantalla y, donde no hay,
     * los del entorno.
     */
    public function leer(): AjustesDeRespaldoData;

    public function guardar(AjustesDeRespaldoData $ajustes): void;
}
