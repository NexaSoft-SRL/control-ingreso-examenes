<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\PlantillaNormaGateway;
use App\Modules\Examenes\Domain\Exceptions\PlantillaAjenaException;

/**
 * Solo se quita la plantilla propia. Los examenes que la tenian marcada
 * conservan la norma con el texto con que se guardo.
 */
final readonly class QuitarPlantillaDeNorma
{
    public function __construct(
        private PlantillaNormaGateway $plantillas,
    ) {}

    /**
     * Falso si la plantilla no existe.
     *
     * @throws PlantillaAjenaException
     */
    public function execute(int $plantillaId, int $usuarioId): bool
    {
        $plantilla = $this->plantillas->buscar($plantillaId);

        if ($plantilla === null) {
            return false;
        }

        if (! $plantilla->esDe($usuarioId)) {
            throw new PlantillaAjenaException($plantilla->esPredefinida());
        }

        return $this->plantillas->quitar($plantillaId, $usuarioId);
    }
}
