<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Contracts\ContextoOperativoPuntoControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class ContextoPuntoControlController
{
    public function __invoke(ContextoOperativoPuntoControl $contexto): JsonResponse
    {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json(['message' => 'No hay una sesión activa.'], 401);
        }

        return response()->json([
            'data' => $contexto->examenesAutorizados($usuarioId),
        ]);
    }
}
