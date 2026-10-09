<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Application\DTOs\EdificioData;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;

interface FuenteUbicacionesGateway
{
    /**
     * Los edificios de una facultad; lista vacia si la fuente no la trae.
     *
     * @return list<EdificioData>
     *
     * @throws FuenteNoDisponibleException si el archivo falta o no se entiende
     */
    public function edificiosDe(string $facultad): array;
}
