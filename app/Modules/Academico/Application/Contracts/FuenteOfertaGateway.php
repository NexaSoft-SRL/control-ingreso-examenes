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
     * La oferta de una facultad (`fcyt`, `fce`, `fhce`, `fach`). Con
     * `$periodo` («1/2026») se lee la guardada de ese período en el
     * historial, no la vigente.
     *
     * @throws FuenteNoDisponibleException si el archivo falta o no se entiende
     */
    public function leer(string $facultad, ?string $periodo = null): OfertaFacultadData;

    /**
     * Una huella de todo lo que se lee al importar esa facultad (su
     * oferta, el pensum y las ubicaciones): si no cambia, la fuente es la
     * misma. Nula si algun archivo falta.
     */
    public function huella(string $facultad): ?string;
}
