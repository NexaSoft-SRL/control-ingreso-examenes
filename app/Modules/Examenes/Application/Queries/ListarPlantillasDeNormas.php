<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\PlantillaNormaGateway;
use App\Modules\Examenes\Application\DTOs\PlantillaNormaData;

/**
 * Las normas que la cuenta puede marcar: las predefinidas y sus plantillas.
 */
final readonly class ListarPlantillasDeNormas
{
    public function __construct(
        private PlantillaNormaGateway $plantillas,
    ) {}

    /**
     * @return list<PlantillaNormaData>
     */
    public function execute(int $usuarioId): array
    {
        return $this->plantillas->visiblesPara($usuarioId);
    }
}
