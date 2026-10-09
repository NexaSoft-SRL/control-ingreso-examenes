<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Application\Queries\ListarCarreras;
use App\Modules\Academico\Http\Requests\FiltroUbicacionRequest;
use Illuminate\Http\JsonResponse;

/**
 * Carreras de la oferta importada: las usan Periodo y el Padron.
 */
final class OfertaController
{
    public function carreras(FiltroUbicacionRequest $request, ListarCarreras $listar): JsonResponse
    {
        return response()->json(['data' => $listar->execute($request->facultad())]);
    }
}
