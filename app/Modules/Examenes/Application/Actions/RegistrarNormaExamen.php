<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\NormaExamenGateway;
use App\Modules\Examenes\Application\DTOs\RegistrarNormaExamenData;

final readonly class RegistrarNormaExamen
{
    public function __construct(
        private NormaExamenGateway $gateway,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function execute(
        int $examenId,
        RegistrarNormaExamenData $data,
        int $usuarioId,
    ): ?array {
        return $this->gateway->registrar($examenId, $data, $usuarioId);
    }
}
