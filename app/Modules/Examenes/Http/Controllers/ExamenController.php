<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\ListarEstudiantesExamen;
use App\Modules\Examenes\Application\Actions\ListarExamenes;
use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\GrupoAsignatura;
use Illuminate\Http\JsonResponse;
use LogicException;

final class ExamenController
{
    public function index(ListarExamenes $listar): JsonResponse
    {
        $data = array_map(
            fn (Examen $examen): array => $this->serializar($examen),
            $listar->execute(),
        );

        return response()->json(['data' => $data]);
    }

    public function estudiantes(ListarEstudiantesExamen $listar): JsonResponse
    {
        return response()->json(['data' => $listar->execute()]);
    }

    public function controlIndex(ListarExamenes $listar): JsonResponse
    {
        return $this->index($listar);
    }

    public function controlEstudiantes(ListarEstudiantesExamen $listar): JsonResponse
    {
        return $this->estudiantes($listar);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Examen $examen): array
    {
        $grupo = $examen->grupo;

        if (! $grupo instanceof GrupoAsignatura) {
            throw new LogicException('El examen no tiene un grupo válido.');
        }

        $asignatura = $grupo->asignatura;
        $docente = $grupo->docente;

        if (! $asignatura instanceof Asignatura || ! $docente instanceof Docente) {
            throw new LogicException('El grupo del examen no tiene asignatura y docente válidos.');
        }

        return [
            'id' => $examen->getKey(),
            'nombre' => $examen->nombre,
            'fecha' => $examen->fecha,
            'hora_inicio' => $examen->hora_inicio,
            'duracion_minutos' => $examen->duracion_minutos,
            'grupo' => [
                'id' => $grupo->getKey(),
                'codigo_grupo' => $grupo->codigo_grupo,
                'asignatura' => [
                    'id' => $asignatura->getKey(),
                    'codigo' => $asignatura->codigo,
                    'nombre' => $asignatura->nombre,
                ],
                'docente' => [
                    'id' => $docente->getKey(),
                    'nombres' => $docente->nombres,
                    'apellidos' => $docente->apellidos,
                ],
            ],
        ];
    }
}
