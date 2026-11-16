<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Actions;

use App\Modules\Reportes\Application\Contracts\AlertaReporteGateway;

final class ProcesarAlertasDeExamen
{
    public function __construct(private readonly AlertaReporteGateway $gateway) {}

    public function execute(): int
    {
        return $this->gateway->procesarCierres();
    }
}
