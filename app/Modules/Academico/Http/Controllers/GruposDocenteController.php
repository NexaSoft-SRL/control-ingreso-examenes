<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Application\Queries\ListarGruposDelDocente;
use App\Modules\Academico\Http\Requests\GruposDelDocenteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Ruta 33: «Mis grupos» del docente que tiene la sesion.
 */
final class GruposDocenteController
{
    public function index(GruposDelDocenteRequest $request, ListarGruposDelDocente $listar): JsonResponse
    {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json($listar->execute($usuarioId, $request->periodoId()));
    }
}
