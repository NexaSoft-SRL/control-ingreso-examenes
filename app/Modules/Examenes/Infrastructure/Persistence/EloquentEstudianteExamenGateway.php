<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\EstudianteExamenGateway;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentEstudianteExamenGateway implements EstudianteExamenGateway
{
    /**
     * @return list<array{id: int|string, nombre: string, codigo_universitario: string|null}>
     */
    public function listarParaNormas(): array
    {
        $estudiantes = DB::table('students')
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellido', 'codigo_universitario'])
            ->map(static function (object $estudiante): array {
                $student = get_object_vars($estudiante);
                $id = $student['id'] ?? null;
                $apellido = $student['apellido'] ?? null;
                $nombre = $student['nombre'] ?? null;
                $codigo = $student['codigo_universitario'] ?? null;

                if ((! is_int($id) && ! is_string($id))
                    || ! is_string($apellido)
                    || ! is_string($nombre)
                    || ($codigo !== null && ! is_string($codigo))) {
                    throw new LogicException('El registro del estudiante tiene datos inválidos.');
                }

                return [
                    'id' => $id,
                    'nombre' => trim($apellido.' '.$nombre),
                    'codigo_universitario' => $codigo,
                ];
            })
            ->all();

        return array_values($estudiantes);
    }
}
