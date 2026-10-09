<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\PlantillaNormaGateway;
use App\Modules\Examenes\Application\DTOs\PlantillaNormaData;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Exceptions\PlantillaAjenaException;

/**
 * Solo se edita la plantilla propia. El cambio rige para los examenes que
 * se registren despues: los ya registrados conservan su texto.
 */
final readonly class EditarPlantillaDeNorma
{
    public function __construct(
        private ComprobarTextoDePlantilla $comprobar,
        private PlantillaNormaGateway $plantillas,
    ) {}

    /**
     * Nulo si la plantilla no existe.
     *
     * @throws DatoInvalidoException
     * @throws PlantillaAjenaException
     */
    public function execute(int $plantillaId, string $texto, int $usuarioId): ?PlantillaNormaData
    {
        $plantilla = $this->plantillas->buscar($plantillaId);

        if ($plantilla === null) {
            return null;
        }

        if (! $plantilla->esDe($usuarioId)) {
            throw new PlantillaAjenaException($plantilla->esPredefinida());
        }

        return $this->plantillas->editar(
            $plantillaId,
            $this->comprobar->execute($texto, $usuarioId, $plantillaId),
            $usuarioId,
        );
    }
}
