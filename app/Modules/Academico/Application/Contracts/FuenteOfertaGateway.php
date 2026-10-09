<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Application\DTOs\OfertaFacultadData;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;

interface FuenteOfertaGateway
{
    /**
     * Las claves de las facultades cuya oferta se importa.
     *
     * @return list<string>
     */
    public function facultades(): array;

    /**
     * La oferta de una facultad (`fcyt`, `fce`, `fhce`, `fach`).
     *
     * @throws FuenteNoDisponibleException si el archivo falta o no se entiende
     */
    public function leer(string $facultad): OfertaFacultadData;
}
