<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Application\Queries\ListarAsignaturasDeOferta;
use App\Modules\Academico\Application\Queries\ListarCarreras;
use App\Modules\Academico\Http\Requests\FiltroOfertaRequest;
use App\Modules\Academico\Http\Requests\FiltroUbicacionRequest;
use App\Modules\Academico\Http\Requests\PaginaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Carreras y asignaturas de la oferta importada.
 */
final class OfertaController
{
    public function carreras(FiltroUbicacionRequest $request, ListarCarreras $listar): JsonResponse
    {
        return response()->json(['data' => $listar->execute($request->facultad())]);
    }

    public function asignaturas(
        FiltroOfertaRequest $request,
        PaginaRequest $pagina,
        ListarAsignaturasDeOferta $listar,
    ): JsonResponse {
        return response()->json(
            $listar->execute($request->filtros(), $pagina->pagina(15))
        );
    }

    public function grupos(
        int $asignatura,
        FiltroOfertaRequest $request,
        PaginaRequest $pagina,
        ListarAsignaturasDeOferta $listar,
    ): JsonResponse {
        if (! $listar->existeAsignatura($asignatura)) {
            return response()->json(
                ['message' => 'Asignatura no encontrada.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return response()->json(
            $listar->grupos(
                $asignatura,
                $request->filtros(),
                $request->buscar(),
                $pagina->pagina(10),
            )
        );
    }

    public function niveles(
        FiltroOfertaRequest $request,
        ListarAsignaturasDeOferta $listar,
    ): JsonResponse {
        return response()->json(['data' => $listar->niveles($request->filtros())]);
    }
}
