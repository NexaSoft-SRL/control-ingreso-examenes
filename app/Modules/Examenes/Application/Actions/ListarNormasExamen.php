<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\NormaExamenGateway;

final readonly class ListarNormasExamen
{
    public function __construct(
        private NormaExamenGateway $gateway,
    ) {}

    /**
     * @return list<array<string, mixed>>|null
     */
    public function execute(int $examenId): ?array
    {
        return $this->gateway->listar($examenId);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function paraEstudiante(int $examenId, int $estudianteId): ?array
    {
        return $this->gateway->listarParaEstudiante($examenId, $estudianteId);
    }
}
