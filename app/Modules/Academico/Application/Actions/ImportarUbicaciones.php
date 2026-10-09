<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Actions;

use App\Modules\Academico\Application\Contracts\FuenteUbicacionesGateway;
use App\Modules\Academico\Application\Contracts\ImportacionGateway;
use App\Modules\Academico\Application\Contracts\UbicacionGateway;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use App\Modules\Academico\Domain\Rules\NombresDeOferta;

/**
 * Deja los edificios de una facultad, con su poligono y su centro, y las
 * aulas de cada uno con su piso. Volver a correrlo no duplica.
 */
final readonly class ImportarUbicaciones
{
    public function __construct(
        private FuenteUbicacionesGateway $fuente,
        private UbicacionGateway $ubicaciones,
        private ImportacionGateway $importaciones,
    ) {}

    /**
     * @return array{edificios: int, aulas: int}
     *
     * @throws FuenteNoDisponibleException
     */
    public function execute(string $facultad): array
    {
        $datos = $this->importaciones->facultad($facultad);

        if ($datos === null) {
            throw new FuenteNoDisponibleException("La facultad «{$facultad}» no está en el catálogo.");
        }

        $edificios = 0;
        $aulas = 0;

        foreach ($this->fuente->edificiosDe($facultad) as $edificio) {
            [$lon, $lat] = $this->centro($edificio->poligono);

            $aulas += $this->ubicaciones->guardarEdificio(
                $datos['id'],
                $facultad.'_'.$edificio->id,
                NombresDeOferta::titulo($edificio->nombre),
                $edificio,
                $lon,
                $lat,
            );

            $edificios++;
        }

        return ['edificios' => $edificios, 'aulas' => $aulas];
    }

    /**
     * Promedio de los vertices; el ultimo no cuenta si repite al primero
     * (el anillo viene cerrado).
     *
     * @param  list<array{0: float, 1: float}>  $poligono
     * @return array{0: float, 1: float}
     */
    private function centro(array $poligono): array
    {
        $ultimo = count($poligono) - 1;

        if ($ultimo > 0 && $poligono[0] === $poligono[$ultimo]) {
            array_pop($poligono);
        }

        if ($poligono === []) {
            return [0.0, 0.0];
        }

        $lon = 0.0;
        $lat = 0.0;

        foreach ($poligono as $punto) {
            $lon += $punto[0];
            $lat += $punto[1];
        }

        return [$lon / count($poligono), $lat / count($poligono)];
    }
}
