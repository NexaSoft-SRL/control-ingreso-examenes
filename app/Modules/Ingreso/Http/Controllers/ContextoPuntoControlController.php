<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Contracts\ContextoOperativoPuntoControl;
use App\Modules\Ingreso\Http\Middleware\ExigirPuesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los examenes de hoy y las aulas cuya puerta opera quien entra: con cuenta,
 * las que atiende; con codigo de aula, la de su acceso, con su nombre y
 * hasta cuando vale.
 */
final class ContextoPuntoControlController
{
    public function __invoke(Request $request, ContextoOperativoPuntoControl $contexto): JsonResponse
    {
        $operador = ExigirPuesto::operador($request);

        if ($operador->usuarioId !== null) {
            return response()->json([
                'data' => $contexto->examenesAutorizados($operador->usuarioId),
            ]);
        }

        return response()->json([
            'data' => $contexto->examenesDelAula((int) $operador->aulaId),
            'acceso' => [
                'nombre' => $operador->nombre,
                'aula_id' => $operador->aulaId,
                'aula' => $operador->aula,
                'examen_id' => $operador->examenId,
                'hasta' => $operador->hasta,
            ],
        ]);
    }
}
