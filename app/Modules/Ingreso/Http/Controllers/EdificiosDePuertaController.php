<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Academico\Application\Contracts\ConsultaAulasGateway;
use Illuminate\Http\JsonResponse;

/**
 * Los edificios para el mapa de la puerta («Su aula sugerida…»). Los
 * alcanza tambien quien entro con codigo de aula, que no puede leer
 * `/api/edificios`; la ruta ya exige operar esa puerta.
 */
final class EdificiosDePuertaController
{
    public function __invoke(int $aula, ConsultaAulasGateway $aulas): JsonResponse
    {
        $edificios = $aulas->edificios(null);
        $longitudes = [];
        $latitudes = [];

        foreach ($edificios as $edificio) {
            foreach ($edificio['poligono'] as [$longitud, $latitud]) {
                $longitudes[] = $longitud;
                $latitudes[] = $latitud;
            }
        }

        // Cambian solo al importar: el navegador puede guardarlos.
        return response()
            ->json([
                'data' => $edificios,
                'meta' => [
                    'caja' => $longitudes === [] || $latitudes === [] ? null : [
                        'lon' => [min($longitudes), max($longitudes)],
                        'lat' => [min($latitudes), max($latitudes)],
                    ],
                ],
            ])
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
