<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaAulasGateway;

/**
 * Los edificios para el mapa del campus, con la caja que los contiene.
 */
final readonly class ListarEdificios
{
    public function __construct(
        private ConsultaAulasGateway $aulas,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{caja: array{lon: array{float, float}, lat: array{float, float}}|null}}
     */
    public function execute(?string $claveFacultad): array
    {
        $edificios = $this->aulas->edificios($claveFacultad);
        $longitudes = [];
        $latitudes = [];

        foreach ($edificios as $edificio) {
            foreach ($edificio['poligono'] as [$longitud, $latitud]) {
                $longitudes[] = $longitud;
                $latitudes[] = $latitud;
            }
        }

        $caja = $longitudes === [] || $latitudes === []
            ? null
            : [
                'lon' => [min($longitudes), max($longitudes)],
                'lat' => [min($latitudes), max($latitudes)],
            ];

        return [
            'data' => $edificios,
            'meta' => ['caja' => $caja],
        ];
    }
}
