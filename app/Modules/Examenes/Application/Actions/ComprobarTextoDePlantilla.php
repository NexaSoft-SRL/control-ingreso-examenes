<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\PlantillaNormaGateway;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;
use App\Modules\Examenes\Domain\Rules\TextoDeNorma;

/**
 * El texto de una plantilla propia: entre 3 y 300 caracteres y distinto de
 * las que la cuenta ya tiene, sin distinguir mayusculas ni tildes.
 */
final readonly class ComprobarTextoDePlantilla
{
    public function __construct(
        private PlantillaNormaGateway $plantillas,
    ) {}

    /**
     * Devuelve el texto listo para guardar.
     *
     * @throws DatoInvalidoException
     */
    public function execute(string $texto, int $usuarioId, ?int $exceptoPlantillaId = null): string
    {
        $texto = TextoDeNorma::limpiar($texto);
        $largo = mb_strlen($texto);

        if ($largo === 0) {
            throw new DatoInvalidoException('texto', 'Obligatorio');
        }

        if ($largo < TextoDeNorma::MINIMO || $largo > TextoDeNorma::MAXIMO) {
            throw new DatoInvalidoException('texto', 'Entre 3 y 300 caracteres');
        }

        foreach ($this->plantillas->visiblesPara($usuarioId) as $plantilla) {
            if (
                $plantilla->esDe($usuarioId)
                && $plantilla->id !== $exceptoPlantillaId
                && TextoDeNorma::sonIguales($plantilla->texto, $texto)
            ) {
                throw new DatoInvalidoException('texto', 'Ya existe');
            }
        }

        return $texto;
    }
}
