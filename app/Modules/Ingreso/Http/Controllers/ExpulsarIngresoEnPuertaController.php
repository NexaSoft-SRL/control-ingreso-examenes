<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\OperacionesPuertaGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
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
        VentanaPuntoControl $ventana,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Obligatorio',
            'motivo.min' => 'Mínimo 5 caracteres',
            'motivo.max' => 'Máximo 500 caracteres',
        ]);

        if ($aula <= 0 || $ingreso <= 0) {
            return response()->json([
                'message' => 'Identificadores no válidos.',
            ], 422);
        }

        if (! is_array($datos)) {
            return response()->json([
                'message' => 'Datos no válidos.',
            ], 422);
        }

        $motivo = $datos['motivo'] ?? null;

        if (! is_string($motivo)) {
            return response()->json([
                'message' => 'Motivo no válido.',
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
                'message' => 'Sin asignación en esta aula.',
            ], 403);
        }

        $horaPrevia = $operaciones->horaExpulsion($ingreso);

        if ($horaPrevia !== null) {
            return response()->json([
                'message' => 'Ya estaba expulsado.',
                'data' => [
                    'ingreso_id' => $ingreso,
                    'expulsado' => true,
                    'hora' => $horaPrevia,
                ],
            ], 409);
        }

        // N3: fuera de la ventana del examen no se expulsa.
        if (! $ventana->estaAbierta($examenId)) {
            return response()->json([
                'message' => 'Fuera del horario del examen.',
            ], 409);
        }

        $creada = $operaciones->expulsar(
            $ingreso,
            $aula,
            $usuarioId,
            $motivo,
        );

        if (! $creada) {
            return response()->json([
                'message' => 'Ya estaba expulsado.',
                'data' => [
                    'ingreso_id' => $ingreso,
                    'expulsado' => true,
                    'hora' => $operaciones->horaExpulsion($ingreso),
                ],
            ], 409);
        }

        $hora = $operaciones->horaExpulsion($ingreso);

        return response()->json([
            'message' => 'Expulsión registrada',
            'data' => [
                'ingreso_id' => $ingreso,
                'expulsado' => true,
                'hora' => $hora,
            ],
        ], 201);
    }
}
