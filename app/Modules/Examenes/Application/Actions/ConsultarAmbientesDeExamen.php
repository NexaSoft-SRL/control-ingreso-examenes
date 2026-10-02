<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AmbienteExamenGateway;
use App\Modules\Examenes\Application\DTOs\AmbienteAsignadoData;
use App\Modules\Examenes\Application\DTOs\OcupacionExamenData;

final readonly class ConsultarAmbientesDeExamen
{
    public function __construct(
        private AmbienteExamenGateway $gateway,
    ) {}

    /**
     * @return list<AmbienteAsignadoData>
     */
    public function execute(int $examenId): array
    {
        return $this->gateway->listarDeExamen($examenId);
    }

    public function ocupacion(int $examenId): OcupacionExamenData
    {
        return $this->gateway->ocupacion($examenId);
    }
}
