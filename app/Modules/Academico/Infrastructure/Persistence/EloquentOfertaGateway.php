<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\OfertaGateway;
use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Escribe la oferta por lotes (una facultad son mas de mil grupos y unas
 * tres mil sesiones): `upsert` por la clave natural de cada tabla.
 */
final class EloquentOfertaGateway implements OfertaGateway
{
    private const LOTE = 500;

    /**
     * @return list<int>
     */
    public function guardarCarreras(int $facultadId, string $sigla, array $carreras): array
    {
        $ids = [];
        $ahora = now();

        foreach ($carreras as $carrera) {
            $codigo = $carrera['codigo'] ?? $this->codigoDeCarreraSinCodigo($facultadId, $sigla, $carrera['nombre']);

            $datos = [
                'facultad_id' => $facultadId,
                'nombre' => $carrera['nombre'],
                'regimen' => ($carrera['anual'] ? RegimenCarrera::Anual : RegimenCarrera::Semestral)->value,
                'updated_at' => $ahora,
            ];

            if (DB::table('carreras')->where('codigo', $codigo)->exists()) {
                DB::table('carreras')->where('codigo', $codigo)->update($datos);
            } else {
                DB::table('carreras')->insert($datos + ['codigo' => $codigo, 'created_at' => $ahora]);
            }

            $ids[] = $this->entero(DB::table('carreras')->where('codigo', $codigo)->value('id'));
        }

        return $ids;
    }

    /**
     * @return array<string, int>
     */
    public function guardarAsignaturas(array $asignaturas): array
    {
        $ahora = now();
        $filas = [];

        foreach ($asignaturas as $codigo => $nombre) {
            $filas[] = [
                'codigo' => (string) $codigo,
                'nombre' => $nombre,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($filas, self::LOTE) as $lote) {
            DB::table('asignaturas')->upsert($lote, ['codigo'], ['nombre', 'updated_at']);
        }

        return $this->idsPorTexto('asignaturas', 'codigo', array_map('strval', array_keys($asignaturas)));
    }

    public function guardarPlan(array $plan): void
    {
        $filas = [];

        foreach ($plan as $fila) {
            $filas[$fila['carrera_id'].'|'.$fila['asignatura_id']] = $fila;
        }

        foreach (array_chunk(array_values($filas), self::LOTE) as $lote) {
            DB::table('plan_estudios')->upsert($lote, ['carrera_id', 'asignatura_id'], ['nivel']);
        }
    }

    /**
     * @return array<string, int>
     */
    public function guardarDocentes(array $docentes): array
    {
        $ahora = now();
        $filas = [];

        foreach ($docentes as $normalizado => $nombre) {
            $filas[] = [
                'nombre_normalizado' => (string) $normalizado,
                'nombre_completo' => $nombre,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        // Al que ya existe no se lo toca: ni su nombre ni su cuenta.
        foreach (array_chunk($filas, self::LOTE) as $lote) {
            DB::table('docentes')->insertOrIgnore($lote);
        }

        return $this->idsPorTexto('docentes', 'nombre_normalizado', array_map('strval', array_keys($docentes)));
    }

    /**
     * @return array<string, int>
     */
    public function guardarAulas(int $facultadId, array $nombres): array
    {
        $ahora = now();
        $filas = [];

        foreach (array_unique($nombres) as $nombre) {
            $filas[] = [
                'nombre' => $nombre,
                'edificio_id' => null,
                'facultad_id' => $facultadId,
                'piso' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        // El aula es una sola aunque la usen dos facultades: la que ya
        // existe queda como esta.
        foreach (array_chunk($filas, self::LOTE) as $lote) {
            DB::table('aulas')->insertOrIgnore($lote);
        }

        return $this->idsPorTexto('aulas', 'nombre', array_values(array_unique($nombres)));
    }

    /**
     * @return list<int>
     */
    public function guardarGrupos(int $facultadId, array $grupos): array
    {
        $ahora = now();
        $filas = [];
        $periodos = [];

        foreach ($grupos as $grupo) {
            $clave = $this->claveDeGrupo($grupo['periodo_id'], $grupo['asignatura_id'], $grupo['codigo']);
            $periodos[$grupo['periodo_id']] = true;

            $filas[$clave] = [
                'periodo_id' => $grupo['periodo_id'],
                'asignatura_id' => $grupo['asignatura_id'],
                'facultad_id' => $facultadId,
                'codigo' => $grupo['codigo'],
                'docente_id' => $grupo['docente_id'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk(array_values($filas), self::LOTE) as $lote) {
            DB::table('grupos')->upsert(
                $lote,
                ['periodo_id', 'asignatura_id', 'facultad_id', 'codigo'],
                ['docente_id', 'updated_at'],
            );
        }

        $guardados = [];

        if ($periodos !== []) {
            $existentes = DB::table('grupos')
                ->where('facultad_id', $facultadId)
                ->whereIn('periodo_id', array_keys($periodos))
                ->get(['id', 'periodo_id', 'asignatura_id', 'codigo']);

            foreach ($existentes as $fila) {
                $clave = $this->claveDeGrupo(
                    $this->entero($fila->periodo_id),
                    $this->entero($fila->asignatura_id),
                    is_string($fila->codigo) ? $fila->codigo : '',
                );

                $guardados[$clave] = $this->entero($fila->id);
            }
        }

        $ids = [];

        foreach ($grupos as $grupo) {
            $clave = $this->claveDeGrupo($grupo['periodo_id'], $grupo['asignatura_id'], $grupo['codigo']);

            if (! isset($guardados[$clave])) {
                throw new RuntimeException("No se pudo guardar el grupo {$grupo['codigo']}.");
            }

            $ids[] = $guardados[$clave];
        }

        return $ids;
    }

    public function reemplazarHorarios(array $grupoIds, array $horarios): void
    {
        foreach (array_chunk($grupoIds, 1000) as $lote) {
            DB::table('horarios')->whereIn('grupo_id', $lote)->delete();
        }

        foreach (array_chunk($horarios, self::LOTE) as $lote) {
            DB::table('horarios')->insert($lote);
        }
    }

    public function eliminarGruposAusentes(int $facultadId, array $periodoIds, array $presentes): int
    {
        if ($periodoIds === []) {
            return 0;
        }

        $presentes = array_flip($presentes);
        $ausentes = [];

        $ids = DB::table('grupos')
            ->where('facultad_id', $facultadId)
            ->whereIn('periodo_id', $periodoIds)
            ->pluck('id');

        foreach ($ids as $id) {
            $id = $this->entero($id);

            if (! isset($presentes[$id])) {
                $ausentes[] = $id;
            }
        }

        $borrados = 0;

        foreach (array_chunk($ausentes, 1000) as $lote) {
            // Un grupo con inscritos o con examen es historia: se queda.
            $borrados += DB::table('grupos')
                ->whereIn('id', $lote)
                ->whereNotIn('id', DB::table('inscripciones')->select('grupo_id'))
                ->whereNotIn('id', DB::table('examen_grupo')->select('grupo_id'))
                ->delete();
        }

        return $borrados;
    }

    /**
     * La carrera que la oferta trae sin codigo y que el pensum no conoce:
     * se la reconoce por su nombre y recibe un codigo propio.
     */
    private function codigoDeCarreraSinCodigo(int $facultadId, string $sigla, string $nombre): string
    {
        $existente = DB::table('carreras')
            ->where('facultad_id', $facultadId)
            ->where('nombre', $nombre)
            ->value('codigo');

        if (is_string($existente)) {
            return $existente;
        }

        $prefijo = 'SIN-'.mb_strtoupper($sigla, 'UTF-8').'-';
        $numero = DB::table('carreras')->where('codigo', 'like', $prefijo.'%')->count();

        do {
            $numero++;
            $codigo = $prefijo.$numero;

            // La columna admite diez caracteres.
            if (strlen($codigo) > 10) {
                $codigo = 'S'.$facultadId.'-'.$numero;
            }
        } while (DB::table('carreras')->where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * @param  list<string>  $valores
     * @return array<string, int>
     */
    private function idsPorTexto(string $tabla, string $columna, array $valores): array
    {
        $ids = [];

        foreach (array_chunk($valores, 1000) as $lote) {
            foreach (DB::table($tabla)->whereIn($columna, $lote)->pluck('id', $columna) as $valor => $id) {
                $ids[(string) $valor] = $this->entero($id);
            }
        }

        return $ids;
    }

    private function claveDeGrupo(int $periodoId, int $asignaturaId, string $codigo): string
    {
        return $periodoId.'|'.$asignaturaId.'|'.$codigo;
    }

    private function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }
}
