<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaAulasGateway;
use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;

/**
 * Los edificios que el mapa de la puerta necesita para señalar otra aula:
 * los de las facultades donde estan el aula y las demas aulas de sus
 * examenes del dia. Tiene la misma forma que `GET /api/edificios`, pero
 * la alcanza tambien quien entro con codigo de aula.
 */
final readonly class ConsultarMapaDePuerta
{
    public function __construct(
        private PuertaGateway $puerta,
        private ConsultaAulasGateway $ubicaciones,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{caja: array{lon: array{float, float}, lat: array{float, float}}|null}}
     */
    public function execute(AulaData $aula, OperadorData $operador): array
    {
        $delDia = $this->puerta->examenesDelDia($aula->id);

        if (! $this->alcance->atiendeAula($operador, $aula->id, $delDia)) {
            throw FueraDeAlcanceException::aula();
        }

        $facultades = $this->puerta->facultadesDelMapa(
            $aula->id,
            array_map(static fn (ExamenData $examen): int => $examen->id, $delDia),
        );

        $edificios = [];
        $longitudes = [];
        $latitudes = [];

        foreach ($facultades as $clave) {
            foreach ($this->ubicaciones->edificios($clave) as $edificio) {
                $edificios[] = $edificio;

                foreach ($edificio['poligono'] as [$longitud, $latitud]) {
                    $longitudes[] = $longitud;
                    $latitudes[] = $latitud;
                }
            }
        }

        return [
            'data' => $edificios,
            'meta' => [
                'caja' => $longitudes === [] || $latitudes === [] ? null : [
                    'lon' => [min($longitudes), max($longitudes)],
                    'lat' => [min($latitudes), max($latitudes)],
                ],
            ],
        ];
    }
}
