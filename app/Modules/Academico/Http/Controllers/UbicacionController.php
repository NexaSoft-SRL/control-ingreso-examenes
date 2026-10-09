<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Application\Queries\ListarAulas;
use App\Modules\Academico\Application\Queries\ListarEdificios;
use App\Modules\Academico\Http\Requests\FiltroUbicacionRequest;
use Illuminate\Http\JsonResponse;

/**
 * Rutas 28 y 29: edificios del campus y aulas.
 */
final class UbicacionController
{
    public function edificios(FiltroUbicacionRequest $request, ListarEdificios $listar): JsonResponse
    {
        // Los edificios cambian solo al importar: el navegador puede
        // guardarlos una hora.
        return response()
            ->json($listar->execute($request->facultad()))
            ->header('Cache-Control', 'private, max-age=3600');
    }

    public function aulas(FiltroUbicacionRequest $request, ListarAulas $listar): JsonResponse
    {
        return response()->json([
            'data' => $listar->execute($request->facultad(), $request->soloUbicadas()),
        ]);
    }
}
