<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\UbicacionGateway;
use App\Modules\Academico\Application\DTOs\EdificioData;
use Illuminate\Support\Facades\DB;

final class EloquentUbicacionGateway implements UbicacionGateway
{
    public function guardarEdificio(
        int $facultadId,
        string $clave,
        string $nombre,
        EdificioData $edificio,
        float $centroLon,
        float $centroLat,
    ): int {
        $ahora = now();

        $datos = [
            'facultad_id' => $facultadId,
            'nombre' => $nombre,
            'poligono' => json_encode($edificio->poligono, JSON_THROW_ON_ERROR),
            'centro_lon' => round($centroLon, 7),
            'centro_lat' => round($centroLat, 7),
            'updated_at' => $ahora,
        ];

        if (DB::table('edificios')->where('clave', $clave)->exists()) {
            DB::table('edificios')->where('clave', $clave)->update($datos);
        } else {
            DB::table('edificios')->insert($datos + ['clave' => $clave, 'created_at' => $ahora]);
        }

        $id = DB::table('edificios')->where('clave', $clave)->value('id');
        $edificioId = is_numeric($id) ? (int) $id : 0;

        $filas = [];

        foreach ($edificio->aulas as $aula) {
            $filas[$aula['nombre']] = [
                'nombre' => $aula['nombre'],
                'edificio_id' => $edificioId,
                'facultad_id' => $facultadId,
                'piso' => $aula['piso'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        if ($filas !== []) {
            // El aula que la oferta ya habia traido sin ubicar pasa a
            // tener edificio.
            DB::table('aulas')->upsert(
                array_values($filas),
                ['nombre'],
                ['edificio_id', 'facultad_id', 'piso', 'updated_at'],
            );
        }

        return count($filas);
    }
}
