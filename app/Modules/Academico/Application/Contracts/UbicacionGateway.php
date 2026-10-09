<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Application\DTOs\EdificioData;

interface UbicacionGateway
{
    /**
     * Crea o actualiza el edificio por su clave y deja sus aulas (por
     * nombre) unidas a el, con su piso. Devuelve cuantas aulas trae.
     */
    public function guardarEdificio(
        int $facultadId,
        string $clave,
        string $nombre,
        EdificioData $edificio,
        float $centroLon,
        float $centroLat,
    ): int;
}
