<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Application\Queries\ListarFacultades;
use Illuminate\Http\JsonResponse;

/**
 * Rutas 18: el catalogo de facultades.
 */
final class FacultadController
{
    public function index(ListarFacultades $listar): JsonResponse
    {
        return response()->json(['data' => $listar->execute()]);
    }
}
