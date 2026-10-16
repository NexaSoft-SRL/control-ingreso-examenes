<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\IncidenciasIngresoGateway;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentIncidenciasIngresoGateway implements IncidenciasIngresoGateway
{
    public function recientes(
        int $examenId,
        int $aulaId,
        int $limite,
    ): array {
        if ($examenId <= 0 || $aulaId <= 0 || $limite < 1 || $limite > 50) {
            throw new InvalidArgumentException(
                'Parámetros de consulta inválidos.',
            );
        }

        $filas = DB::table('incidencias_ingreso as i')
            ->leftJoin('estudiantes as e', 'e.id', '=', 'i.estudiante_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'i.registrado_por')
            ->where('i.examen_id', $examenId)
            ->where('i.aula_id', $aulaId)
            ->orderByDesc('i.registrado_en')
            ->orderByDesc('i.id')
            ->limit($limite)
            ->get([
                'i.id as incidencia_id',
                'i.estudiante_id',
                'e.codigo_universitario as codigo',
                'e.nombres',
                'e.apellidos',
                'i.aula_id',
                'i.tipo_rechazo',
                'i.detalle',
                'i.registrado_en',
                'u.nombre as operador',
            ]);

        $resultado = [];

        foreach ($filas as $fila) {
            $resultado[] = [
                'incidencia_id' => (int) $fila->incidencia_id,
                'estudiante_id' => $fila->estudiante_id === null
                    ? null : (int) $fila->estudiante_id,
                'codigo' => $fila->codigo,
                'nombres' => $fila->nombres,
                'apellidos' => $fila->apellidos,
                'aula_id' => (int) $fila->aula_id,
                'tipo_rechazo' => $fila->tipo_rechazo,
                'detalle' => $fila->detalle,
                'registrado_en' => (string) $fila->registrado_en,
                'operador' => $fila->operador,
            ];
        }

        return $resultado;
    }
}
