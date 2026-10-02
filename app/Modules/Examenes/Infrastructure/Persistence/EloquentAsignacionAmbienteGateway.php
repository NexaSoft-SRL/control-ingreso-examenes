<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\AsignacionAmbienteGateway;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentAsignacionAmbienteGateway implements AsignacionAmbienteGateway
{
    public function existeExamen(int $examenId): bool
    {
        return DB::table('examenes')->where('id', $examenId)->exists();
    }

    /** @return list<array<string, mixed>> */
    public function candidatos(int $examenId): array
    {
        $filas = DB::table('students as s')
            ->select(['s.id', 's.codigo_universitario', 's.nombre', 's.apellido', 's.carrera', 's.ci'])
            ->join('habilitaciones_examen as h', 'h.estudiante_id', '=', 's.id')
            ->leftJoin('asignaciones_ambiente as a', function (JoinClause $join) use ($examenId): void {
                $join->on('a.estudiante_id', '=', 's.id')
                    ->where('a.examen_id', '=', $examenId);
            })
            ->where('h.examen_id', $examenId)
            ->where('h.estado', 'HABILITADO')
            ->where('s.activo', true)
            ->whereNull('a.id')
            ->orderBy('s.apellido')
            ->orderBy('s.nombre')
            ->get();

        $candidatos = [];

        foreach ($filas as $fila) {
            $candidatos[] = [
                'id' => self::entero($fila->id),
                'codigo_universitario' => $fila->codigo_universitario,
                'nombre' => $fila->nombre,
                'apellido' => $fila->apellido,
                'carrera' => $fila->carrera,
                'ci' => $fila->ci,
            ];
        }

        return $candidatos;
    }

    /** @return list<array<string, mixed>> */
    public function ambientes(int $examenId): array
    {
        return $this->ambientesConOcupacion($examenId, null);
    }

    /** @return array<string, mixed> */
    public function detalleAmbiente(int $examenId, int $ambienteId): array
    {
        return $this->ambientesConOcupacion($examenId, $ambienteId)[0] ?? [];
    }

    public function capacidadAmbiente(int $ambienteId): ?int
    {
        $capacidad = DB::table('ambientes')->where('id', $ambienteId)->value('capacidad');

        return $capacidad === null ? null : self::entero($capacidad);
    }

    public function ocupados(int $examenId, int $ambienteId): int
    {
        return DB::table('asignaciones_ambiente')
            ->where('examen_id', $examenId)
            ->where('ambiente_id', $ambienteId)
            ->count();
    }

    /** @return list<int> */
    public function idsHabilitados(int $examenId): array
    {
        $ids = DB::table('habilitaciones_examen as h')
            ->join('students as s', 's.id', '=', 'h.estudiante_id')
            ->where('h.examen_id', $examenId)
            ->where('h.estado', 'HABILITADO')
            ->where('s.activo', true)
            ->pluck('h.estudiante_id');

        return self::enteros($ids->all());
    }

    /**
     * @param  list<int>  $estudianteIds
     * @return list<int>
     */
    public function idsYaAsignados(int $examenId, array $estudianteIds): array
    {
        $ids = DB::table('asignaciones_ambiente')
            ->where('examen_id', $examenId)
            ->whereIn('estudiante_id', $estudianteIds)
            ->pluck('estudiante_id');

        return self::enteros($ids->all());
    }

    /** @param list<int> $estudianteIds */
    public function asignar(int $examenId, int $ambienteId, array $estudianteIds, ?int $usuarioId): void
    {
        DB::transaction(function () use ($examenId, $ambienteId, $estudianteIds, $usuarioId): void {
            $ahora = now();

            foreach ($estudianteIds as $estudianteId) {
                DB::table('asignaciones_ambiente')->insert([
                    'examen_id' => $examenId,
                    'ambiente_id' => $ambienteId,
                    'estudiante_id' => $estudianteId,
                    'usuario_id' => $usuarioId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        });
    }

    public function quitar(int $examenId, int $ambienteId, int $estudianteId): int
    {
        return DB::table('asignaciones_ambiente')
            ->where('examen_id', $examenId)
            ->where('ambiente_id', $ambienteId)
            ->where('estudiante_id', $estudianteId)
            ->delete();
    }

    /** @return list<array<string, mixed>> */
    private function ambientesConOcupacion(int $examenId, ?int $ambienteId): array
    {
        $consulta = DB::table('ambientes as a')
            ->leftJoin('asignaciones_ambiente as asig', function (JoinClause $join) use ($examenId): void {
                $join->on('asig.ambiente_id', '=', 'a.id')
                    ->where('asig.examen_id', '=', $examenId);
            })
            ->select([
                'a.id',
                'a.nombre',
                'a.ubicacion',
                'a.capacidad',
                'a.estado',
                DB::raw('COUNT(asig.id) as ocupados'),
            ])
            ->groupBy(['a.id', 'a.nombre', 'a.ubicacion', 'a.capacidad', 'a.estado'])
            ->orderBy('a.nombre');

        if ($ambienteId !== null) {
            $consulta->where('a.id', $ambienteId);
        }

        $ambientes = [];

        foreach ($consulta->get() as $fila) {
            $id = self::entero($fila->id);
            $capacidad = self::entero($fila->capacidad);
            $ocupados = self::entero($fila->ocupados);

            $ambientes[] = [
                'id' => $id,
                'nombre' => $fila->nombre,
                'ubicacion' => $fila->ubicacion,
                'capacidad' => $capacidad,
                'estado' => $fila->estado,
                'ocupados' => $ocupados,
                'disponible' => max($capacidad - $ocupados, 0),
                'estudiantes' => $this->estudiantesDelAmbiente($examenId, $id),
            ];
        }

        return $ambientes;
    }

    /** @return list<array<string, mixed>> */
    private function estudiantesDelAmbiente(int $examenId, int $ambienteId): array
    {
        $filas = DB::table('asignaciones_ambiente as a')
            ->join('students as s', 's.id', '=', 'a.estudiante_id')
            ->where('a.ambiente_id', $ambienteId)
            ->where('a.examen_id', $examenId)
            ->orderBy('s.apellido')
            ->orderBy('s.nombre')
            ->get(['s.id', 's.codigo_universitario', 's.nombre', 's.apellido', 's.carrera']);

        $estudiantes = [];

        foreach ($filas as $fila) {
            $estudiantes[] = [
                'id' => self::entero($fila->id),
                'codigo_universitario' => $fila->codigo_universitario,
                'nombre' => $fila->nombre,
                'apellido' => $fila->apellido,
                'carrera' => $fila->carrera,
            ];
        }

        return $estudiantes;
    }

    /**
     * @param  array<array-key, mixed>  $valores
     * @return list<int>
     */
    private static function enteros(array $valores): array
    {
        $enteros = [];

        foreach ($valores as $valor) {
            $enteros[] = self::entero($valor);
        }

        return $enteros;
    }

    private static function entero(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && ctype_digit($valor)) {
            return (int) $valor;
        }

        throw new LogicException('La base de datos devolvió un número inválido.');
    }
}
