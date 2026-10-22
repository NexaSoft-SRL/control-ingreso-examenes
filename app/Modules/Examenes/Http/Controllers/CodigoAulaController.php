<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\Contracts\CodigoAulaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class CodigoAulaController
{
    public function show(
        int $examen,
        int $aula,
        AlcanceExamenGateway $alcance,
        CodigoAulaGateway $gateway,
    ): JsonResponse {
        $usuarioId = (int) Auth::id();

        if (! $alcance->esDocenteDelExamen($usuarioId, $examen)) {
            return response()->json(['message' => 'Examen no autorizado.'], Response::HTTP_FORBIDDEN);
        }

        if (! $gateway->aulaPerteneceAlExamen($examen, $aula)) {
            return response()->json(['message' => 'El aula no pertenece al examen.'], Response::HTTP_NOT_FOUND);
        }

        $datos = $gateway->consultar($examen, $aula);

        return response()->json([
            'data' => [
                'codigo' => $datos['codigo'],
                'generado_en' => $datos['generado_en'],
            ],
        ]);
    }

    public function store(
        int $examen,
        int $aula,
        AlcanceExamenGateway $alcance,
        CodigoAulaGateway $gateway,
    ): JsonResponse {
        $usuarioId = (int) Auth::id();

        if (! $alcance->esDocenteDelExamen($usuarioId, $examen)) {
            return response()->json(['message' => 'Examen no autorizado.'], Response::HTTP_FORBIDDEN);
        }

        if (! $gateway->aulaPerteneceAlExamen($examen, $aula)) {
            return response()->json(['message' => 'El aula no pertenece al examen.'], Response::HTTP_NOT_FOUND);
        }

        $resultado = $gateway->generar($examen, $aula, $usuarioId);

        $mensaje = $resultado['regenerado'] ? 'Código regenerado' : 'Código generado';
        $status = $resultado['regenerado'] ? Response::HTTP_OK : Response::HTTP_CREATED;

        return response()->json([
            'message' => $mensaje,
            'data' => [
                'codigo' => $resultado['codigo'],
                'generado_en' => $resultado['generado_en'],
            ],
        ], $status);
    }
}
