<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\IngresosRecientesGateway;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentIngresosRecientesGateway implements IngresosRecientesGateway
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

        $filas = DB::table('ingresos as i')
            ->join('estudiantes as e', 'e.id', '=', 'i.estudiante_id')
            ->leftJoin('expulsiones as ex', 'ex.ingreso_id', '=', 'i.id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'i.registrado_por')
            ->where('i.examen_id', $examenId)
            ->where('i.aula_id', $aulaId)
            ->orderByDesc('i.registrado_en')
            ->orderByDesc('i.id')
            ->limit($limite)
            ->get([
                'i.id as ingreso_id',
                'ex.id as expulsion_id',
                'i.estudiante_id',
                'e.codigo_universitario as codigo',
                'e.nombres',
                'e.apellidos',
                'i.aula_id',
                'i.registrado_en',
                'u.nombre as operador',
            ]);

        $resultado = [];

        foreach ($filas as $fila) {
            /**
             * @var object{
             *   ingreso_id: int,
             *   expulsion_id: int|null,
             *   estudiante_id: int,
             *   codigo: string,
             *   nombres: string,
             *   apellidos: string,
             *   aula_id: int|null,
             *   registrado_en: string,
             *   operador: string|null
             * } $fila
             */
            $resultado[] = [
                'ingreso_id' => $fila->ingreso_id,
                'expulsado' => $fila->expulsion_id !== null,
                'estudiante_id' => $fila->estudiante_id,
                'codigo' => $fila->codigo,
                'nombres' => $fila->nombres,
                'apellidos' => $fila->apellidos,
                'aula_id' => $fila->aula_id,
                'registrado_en' => $fila->registrado_en,
                'operador' => $fila->operador,
            ];
        }

        return $resultado;
    }
}
