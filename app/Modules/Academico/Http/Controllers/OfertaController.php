<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Application\Queries\ListarAsignaturas;
use App\Modules\Academico\Application\Queries\ListarCarreras;
use App\Modules\Academico\Application\Queries\ListarGruposDeAsignatura;
use App\Modules\Academico\Http\Requests\FiltroUbicacionRequest;
use App\Modules\Academico\Http\Requests\ListarAsignaturasRequest;
use App\Modules\Academico\Http\Requests\ListarGruposDeAsignaturaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Rutas 25 a 27: carreras, asignaturas y grupos de la oferta importada.
 */
final class OfertaController
{
    public function carreras(FiltroUbicacionRequest $request, ListarCarreras $listar): JsonResponse
    {
        return response()->json(['data' => $listar->execute($request->facultad())]);
    }

    public function asignaturas(ListarAsignaturasRequest $request, ListarAsignaturas $listar): JsonResponse
    {
        return response()->json($listar->execute($request->toData(), $request->pagina(15)));
    }

    public function grupos(
        int $asignatura,
        ListarGruposDeAsignaturaRequest $request,
        ListarGruposDeAsignatura $listar,
    ): JsonResponse {
        $grupos = $listar->execute(
            $asignatura,
            $request->periodoId(),
            $request->facultad(),
            $request->buscar(),
            $request->pagina(10),
        );

        if ($grupos === null) {
            return response()->json([
                'message' => 'Asignatura no encontrada.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json($grupos);
    }
}
