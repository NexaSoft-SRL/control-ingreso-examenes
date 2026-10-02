<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Actions;

use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\ConsultaHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\ExamenHabilitacionData;

final readonly class ConsultarHabilitacion
{
    public function __construct(
        private HabilitacionGateway $gateway,
    ) {}

    public function existeExamen(int $examenId): bool
    {
        return $this->gateway->existeExamen($examenId);
    }

    /** @return list<ExamenHabilitacionData> */
    public function listarExamenes(): array
    {
        return $this->gateway->listarExamenes();
    }

    /**
     * El mismo valor puede ser el código de un estudiante y el documento de
     * otro, por eso la respuesta admite más de una coincidencia.
     *
     * @return list<ConsultaHabilitacionData>
     */
    public function porIdentificador(int $examenId, string $identificador): array
    {
        return $this->gateway->consultarPorIdentificador($examenId, $identificador);
    }
}
