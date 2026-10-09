<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\PlantillaNormaGateway;
use App\Modules\Examenes\Application\DTOs\PlantillaNormaData;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;

/**
 * Una plantilla propia: queda disponible para marcarla en los examenes que
 * la cuenta registre.
 */
final readonly class CrearPlantillaDeNorma
{
    public function __construct(
        private ComprobarTextoDePlantilla $comprobar,
        private PlantillaNormaGateway $plantillas,
    ) {}

    /**
     * @throws DatoInvalidoException
     */
    public function execute(string $texto, int $usuarioId): PlantillaNormaData
    {
        return $this->plantillas->crear(
            $usuarioId,
            $this->comprobar->execute($texto, $usuarioId),
        );
    }
}
