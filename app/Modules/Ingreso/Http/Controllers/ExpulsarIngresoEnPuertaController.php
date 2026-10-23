<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\OperacionesPuertaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ExpulsarIngresoEnPuertaController
{
    public function __invoke(
        Request $request,
        int $aula,
        int $ingreso,
        AlcancePuntoControl $alcance,
        OperacionesPuertaGateway $operaciones,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($aula <= 0 || $ingreso <= 0) {
            return response()->json([
                'message' => 'Identificadores inválidos.',
            ], 422);
        }

        if (! is_array($datos)) {
            return response()->json([
                'message' => 'Datos inválidos.',
            ], 422);
        }

        $motivo = $datos['motivo'] ?? null;

        if (! is_string($motivo)) {
            return response()->json([
                'message' => 'Motivo inválido.',
            ], 422);
        }

        $examenId = $operaciones->examenDeIngreso(
            $ingreso,
            $aula,
        );

        if ($examenId === null) {
            return response()->json([
                'message' => 'Ingreso no encontrado.',
            ], 404);
        }

        if (! $alcance->puedeOperar(
            $usuarioId,
            $examenId,
            $aula,
        )) {
            return response()->json([
                'message' => 'No atiendes este punto de control.',
            ], 403);
        }

        $creada = $operaciones->expulsar(
            $ingreso,
            $aula,
            $usuarioId,
            $motivo,
        );

        if (! $creada) {
            return response()->json([
                'message' => 'El ingreso ya fue expulsado.',
            ], 409);
        }

        return response()->json([
            'message' => 'Expulsión registrada.',
            'data' => [
                'ingreso_id' => $ingreso,
                'expulsado' => true,
            ],
        ], 201);
    }
}
