<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use Illuminate\Support\Facades\DB;

final class EloquentExamenesDePuerta implements ExamenesDePuerta
{
    public function examenesDelAula(
        int $aulaId,
        string $fecha,
    ): array {
        $filas = DB::table('examen_aula as asignacion')
            ->join(
                'examenes as examen',
                'examen.id',
                '=',
                'asignacion.examen_id',
            )
            ->where('asignacion.aula_id', $aulaId)
            ->whereBetween('examen.fecha', [
                $fecha,
                (new \DateTimeImmutable($fecha))
                    ->modify('+1 day')
                    ->format('Y-m-d'),
            ])
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->orderBy('examen.id')
            ->pluck('examen.id');

        $ids = [];

        foreach ($filas as $valor) {
            if (! is_int($valor) && ! is_string($valor)) {
                throw new \UnexpectedValueException(
                    'El identificador de examen no es válido.'
                );
            }

            $id = filter_var(
                $valor,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );

            if ($id === false) {
                throw new \UnexpectedValueException(
                    'El identificador de examen no es válido.'
                );
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
