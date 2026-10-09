<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Import;

use App\Modules\Academico\Application\Contracts\FuenteUbicacionesGateway;
use App\Modules\Academico\Application\DTOs\EdificioData;

/**
 * Lee `locations.json`: una clave por facultad con su lista de edificios.
 * Un edificio trae sus aulas sueltas (`aulas`) o por piso (`pisos`).
 */
final class LectorUbicacionesGenda implements FuenteUbicacionesGateway
{
    /**
     * @return list<EdificioData>
     */
    public function edificiosDe(string $facultad): array
    {
        $datos = LectorJson::leer(LectorJson::rutaGenda('locations.json'));
        $lista = $datos[$facultad] ?? null;

        if (! is_array($lista)) {
            return [];
        }

        $edificios = [];

        foreach ($lista as $edificio) {
            if (! is_array($edificio)) {
                continue;
            }

            $id = $edificio['id'] ?? null;
            $nombre = $edificio['nombre'] ?? null;
            $poligono = $this->poligono($edificio['polygon'] ?? null);

            if (! is_string($id) || $id === '' || ! is_string($nombre) || count($poligono) < 3) {
                continue;
            }

            $edificios[] = new EdificioData(
                id: $id,
                nombre: trim($nombre),
                poligono: $poligono,
                aulas: $this->aulas($edificio),
            );
        }

        return $edificios;
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private function poligono(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $puntos = [];

        foreach ($valor as $punto) {
            if (! is_array($punto)) {
                continue;
            }

            $lon = $punto[0] ?? null;
            $lat = $punto[1] ?? null;

            if ((is_float($lon) || is_int($lon)) && (is_float($lat) || is_int($lat))) {
                $puntos[] = [(float) $lon, (float) $lat];
            }
        }

        return $puntos;
    }

    /**
     * @param  array<mixed>  $edificio
     * @return list<array{nombre: string, piso: string|null}>
     */
    private function aulas(array $edificio): array
    {
        $aulas = [];

        foreach ($this->nombres($edificio['aulas'] ?? null) as $nombre) {
            $aulas[$nombre] = ['nombre' => $nombre, 'piso' => null];
        }

        $pisos = $edificio['pisos'] ?? null;

        foreach (is_array($pisos) ? $pisos : [] as $piso) {
            if (! is_array($piso)) {
                continue;
            }

            $nombrePiso = $piso['nombre'] ?? null;
            $nombrePiso = is_string($nombrePiso) && trim($nombrePiso) !== '' ? trim($nombrePiso) : null;

            foreach ($this->nombres($piso['aulas'] ?? null) as $nombre) {
                $aulas[$nombre] = ['nombre' => $nombre, 'piso' => $nombrePiso];
            }
        }

        return array_values($aulas);
    }

    /**
     * @return list<string>
     */
    private function nombres(mixed $valor): array
    {
        $nombres = [];

        foreach (is_array($valor) ? $valor : [] as $nombre) {
            if ((is_string($nombre) || is_int($nombre)) && trim((string) $nombre) !== '') {
                $nombres[] = trim((string) $nombre);
            }
        }

        return $nombres;
    }
}
