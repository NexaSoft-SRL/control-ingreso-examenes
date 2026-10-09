<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\DTOs\GrupoDeExamenData;
use App\Modules\Examenes\Application\Queries\ConsultarOpcionesDeAulas;
use App\Modules\Examenes\Application\Queries\ConsultarOpcionesDeGrupos;
use App\Modules\Examenes\Http\Requests\OpcionesDeAulasRequest;
use App\Modules\Examenes\Http\Requests\OpcionesDeGruposRequest;
use Illuminate\Http\JsonResponse;

final class OpcionesExamenController
{
    use RespuestasDeExamen;

    public function grupos(OpcionesDeGruposRequest $request, ConsultarOpcionesDeGrupos $consultar): JsonResponse
    {
        $opciones = $consultar->execute($request->integer('asignatura_id'), $this->usuarioId());

        $serializar = static fn (GrupoDeExamenData $grupo): array => [
            'id' => $grupo->id,
            'codigo' => $grupo->codigo,
            'docente' => $grupo->docente,
            'inscritos' => $grupo->inscritos,
            'periodo' => $grupo->periodoCodigo,
        ];

        return response()->json([
            'propios' => array_map($serializar, $opciones->propios),
            'otros' => array_map($serializar, $opciones->otros),
        ]);
    }

    public function aulas(OpcionesDeAulasRequest $request, ConsultarOpcionesDeAulas $consultar): JsonResponse
    {
        $opciones = $consultar->execute(
            $request->grupos(),
            $request->texto('fecha'),
            $request->texto('hora_inicio'),
            $request->entero('duracion_minutos'),
            $request->entero('examen_id'),
        );

        return response()->json([
            'sugeridas' => $opciones->sugeridas,
            // Un objeto por id de aula, tambien cuando no hay ninguna.
            'compartidas' => (object) $opciones->compartidas,
        ]);
    }
}
