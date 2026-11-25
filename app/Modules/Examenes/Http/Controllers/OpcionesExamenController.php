<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\DTOs\GrupoDeExamenData;
use App\Modules\Examenes\Application\Queries\ConsultarChoquesDeEstudiantes;
use App\Modules\Examenes\Application\Queries\ConsultarGruposConExamenDeTipo;
use App\Modules\Examenes\Application\Queries\ConsultarOpcionesDeAulas;
use App\Modules\Examenes\Application\Queries\ConsultarOpcionesDeGrupos;
use App\Modules\Examenes\Http\Requests\OpcionesDeAulasRequest;
use App\Modules\Examenes\Http\Requests\OpcionesDeChoquesRequest;
use App\Modules\Examenes\Http\Requests\OpcionesDeGruposRequest;
use App\Modules\Examenes\Http\Requests\OpcionesDeOcupadosRequest;
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
            'docente_usuario_id' => $grupo->docenteUsuarioId,
            'inscritos' => $grupo->inscritos,
            'periodo' => $grupo->periodoCodigo,
        ];

        return response()->json([
            'propios' => array_map($serializar, $opciones->propios),
            'otros' => array_map($serializar, $opciones->otros),
        ]);
    }

    /**
     * Aviso: los estudiantes de esos grupos que ya tienen otro examen a esa
     * hora. No bloquea el registro.
     */
    public function choques(OpcionesDeChoquesRequest $request, ConsultarChoquesDeEstudiantes $consultar): JsonResponse
    {
        $choques = $consultar->execute(
            $request->grupos(),
            $request->texto('fecha'),
            $request->texto('hora_inicio'),
            (int) $request->entero('duracion_minutos'),
            $request->entero('examen_id'),
        );

        return response()->json([
            'total' => $choques->total,
            'estudiantes' => $choques->estudiantes,
        ]);
    }

    /**
     * Los grupos elegidos que ya tienen un examen de ese tipo: el registro los
     * rechazaria, el asistente lo dice en el paso de grupos.
     */
    public function ocupados(OpcionesDeOcupadosRequest $request, ConsultarGruposConExamenDeTipo $consultar): JsonResponse
    {
        return response()->json([
            'ocupados' => $consultar->execute($request->grupos(), $request->tipo(), $request->examenId()),
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
            'clases' => (object) $opciones->clases,
            'examenes' => (object) $opciones->examenes,
            'compartidas' => (object) $opciones->compartidas,
        ]);
    }
}
