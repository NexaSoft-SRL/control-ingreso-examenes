<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

/**
 * Descripcion legible de una operacion de la puerta para la bitacora
 * (BUG-15): «202210098 Vega Escóbar Silvia · Aula 691A · Introducción a la
 * Programación», nunca ids internos.
 */
final class DescripcionPuerta
{
    public static function de(?int $examenId, int $aulaId, ?int $estudianteId): string
    {
        $partes = [];

        if ($estudianteId !== null) {
            $estudiante = DB::table('estudiantes')
                ->where('id', $estudianteId)
                ->first(['codigo_universitario', 'apellidos', 'nombres']);

            if ($estudiante !== null) {
                $partes[] = trim(sprintf(
                    '%s %s %s',
                    self::texto(self::campo($estudiante, 'codigo_universitario')),
                    self::texto(self::campo($estudiante, 'apellidos')),
                    self::texto(self::campo($estudiante, 'nombres')),
                ));
            }
        }

        $aula = DB::table('aulas')->where('id', $aulaId)->value('nombre');

        if (is_string($aula)) {
            $partes[] = 'Aula '.$aula;
        }

        if ($examenId !== null) {
            $asignatura = DB::table('examenes as e')
                ->join('asignaturas as s', 's.id', '=', 'e.asignatura_id')
                ->where('e.id', $examenId)
                ->value('s.nombre');

            if (is_string($asignatura)) {
                $partes[] = $asignatura;
            }
        }

        return implode(' · ', $partes);
    }

    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    private static function campo(object $fila, string $nombre): mixed
    {
        return get_object_vars($fila)[$nombre] ?? null;
    }
}
