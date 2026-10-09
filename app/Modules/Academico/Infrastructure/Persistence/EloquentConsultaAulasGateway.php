<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\ConsultaAulasGateway;
use Illuminate\Support\Facades\DB;

final class EloquentConsultaAulasGateway implements ConsultaAulasGateway
{
    use LeeFilas;

    public function edificios(?string $claveFacultad): array
    {
        $consulta = DB::table('edificios as e')
            ->join('facultades as f', 'f.id', '=', 'e.facultad_id')
            ->orderBy('f.orden')
            ->orderByRaw($this->sinTildes('e.nombre'))
            ->orderBy('e.id');

        if ($this->hayTexto($claveFacultad)) {
            $consulta->where('f.clave', mb_strtolower((string) $claveFacultad));
        }

        $filas = $consulta->get([
            'e.id', 'e.clave', 'e.nombre', 'e.poligono', 'e.centro_lon', 'e.centro_lat', 'f.sigla',
        ]);

        $ids = [];

        foreach ($filas as $fila) {
            $ids[] = $this->entero($fila->id);
        }

        [$aulas, $pisos] = $this->aulasPorEdificio($ids);
        $edificios = [];

        foreach ($filas as $fila) {
            $id = $this->entero($fila->id);
            $porPiso = [];

            foreach ($pisos[$id] ?? [] as $piso => $nombres) {
                $porPiso[] = ['nombre' => (string) $piso, 'aulas' => $nombres];
            }

            $edificios[] = [
                'id' => $id,
                'clave' => $this->cadena($fila->clave),
                'facultad' => $this->cadena($fila->sigla),
                'nombre' => $this->cadena($fila->nombre),
                'poligono' => $this->poligono($fila->poligono),
                'centro' => [(float) $this->cadena($fila->centro_lon), (float) $this->cadena($fila->centro_lat)],
                'aulas' => $aulas[$id] ?? [],
                'pisos' => $porPiso,
            ];
        }

        return $edificios;
    }

    public function aulas(?string $claveFacultad, bool $soloUbicadas): array
    {
        $consulta = DB::table('aulas as a')
            ->leftJoin('edificios as e', 'e.id', '=', 'a.edificio_id')
            ->leftJoin('facultades as f', 'f.id', '=', 'a.facultad_id');

        if ($this->hayTexto($claveFacultad)) {
            $consulta->where('f.clave', mb_strtolower((string) $claveFacultad));
        }

        if ($soloUbicadas) {
            $consulta->whereNotNull('a.edificio_id');
        }

        $filas = $consulta->get([
            'a.id', 'a.nombre', 'a.edificio_id', 'e.nombre as edificio', 'a.piso', 'f.sigla',
        ]);

        $aulas = [];

        foreach ($filas as $fila) {
            $aulas[] = [
                'id' => $this->entero($fila->id),
                'nombre' => $this->cadena($fila->nombre),
                'edificio_id' => $this->enteroONulo($fila->edificio_id),
                'edificio' => $this->texto($fila->edificio),
                'piso' => $this->texto($fila->piso),
                'facultad' => $this->texto($fila->sigla),
            ];
        }

        usort($aulas, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));

        return $aulas;
    }

    /**
     * @param  list<int>  $edificioIds
     * @return array{array<int, list<string>>, array<int, array<string, list<string>>>}
     */
    private function aulasPorEdificio(array $edificioIds): array
    {
        if ($edificioIds === []) {
            return [[], []];
        }

        $nombres = [];

        foreach (DB::table('aulas')->whereIn('edificio_id', $edificioIds)->get(['edificio_id', 'nombre', 'piso']) as $fila) {
            $nombres[] = [
                $this->entero($fila->edificio_id),
                $this->cadena($fila->nombre),
                $this->texto($fila->piso),
            ];
        }

        usort($nombres, static fn (array $a, array $b): int => strnatcasecmp($a[1], $b[1]));

        $todas = [];
        $pisos = [];

        foreach ($nombres as [$edificioId, $nombre, $piso]) {
            $todas[$edificioId][] = $nombre;

            if ($piso !== null && $piso !== '') {
                $pisos[$edificioId][$piso][] = $nombre;
            }
        }

        foreach ($pisos as $edificioId => $porPiso) {
            uksort($porPiso, static fn (int|string $a, int|string $b): int => strnatcasecmp((string) $a, (string) $b));
            $pisos[$edificioId] = $porPiso;
        }

        return [$todas, $pisos];
    }

    /**
     * @return list<array{float, float}>
     */
    private function poligono(mixed $valor): array
    {
        $puntos = is_string($valor) ? json_decode($valor, true) : $valor;
        $poligono = [];

        if (! is_array($puntos)) {
            return $poligono;
        }

        foreach ($puntos as $punto) {
            if (is_array($punto) && is_numeric($punto[0] ?? null) && is_numeric($punto[1] ?? null)) {
                $poligono[] = [(float) $punto[0], (float) $punto[1]];
            }
        }

        return $poligono;
    }
}
