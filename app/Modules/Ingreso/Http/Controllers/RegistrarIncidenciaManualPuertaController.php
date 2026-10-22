<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\IncidenciaManualPuertaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class RegistrarIncidenciaManualPuertaController
{
    public function __invoke(
        Request $request,
        int $aula,
        ExamenGateway $examenes,
        AlcancePuntoControl $alcance,
        IncidenciaManualPuertaGateway $incidencias,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        if ($aula <= 0) {
            return response()->json([
                'message' => 'Aula inválida.',
            ], 422);
        }

        $datos = $request->validate([
            'examen_id' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', 'string', 'min:3', 'max:60'],
            'detalle' => ['required', 'string', 'min:5', 'max:2000'],
            'estudiante_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:estudiantes,id',
                'prohibits:codigo_universitario',
            ],
            'codigo_universitario' => [
                'nullable',
                'string',
                'regex:/^[0-9]{5,10}$/',
                'prohibits:estudiante_id',
            ],
        ]);

        if (! is_array($datos)) {
            return response()->json([
                'message' => 'Datos de incidencia inválidos.',
            ], 422);
        }

        $examenIdValor = $datos['examen_id'] ?? null;

        if (! is_int($examenIdValor)) {
            return response()->json([
                'message' => 'Examen inválido.',
            ], 422);
        }

        $examenId = $examenIdValor;

        $examen = $examenes->actual($examenId);

        if ($examen === null || ! in_array($aula, $examen->aulas, true)) {
            return response()->json([
                'message' => 'El examen no corresponde a esta aula.',
            ], 404);
        }

        if (! $alcance->puedeOperar($usuarioId, $examenId, $aula)) {
            return response()->json([
                'message' => 'No atiendes este examen en esta aula.',
            ], 403);
        }

        $tipoValor = $datos['tipo'] ?? null;
        $detalleValor = $datos['detalle'] ?? null;

        if (! is_string($tipoValor) || ! is_string($detalleValor)) {
            return response()->json([
                'message' => 'Tipo o detalle inválidos.',
            ], 422);
        }

        $tipo = trim($tipoValor);
        $detalle = trim($detalleValor);

        if (
            mb_strlen($tipo) < 3
            || mb_strlen($detalle) < 5
        ) {
            return response()->json([
                'message' => 'El tipo y el detalle deben contener texto válido.',
                'errors' => [
                    'detalle' => [
                        'El detalle debe tener al menos cinco caracteres.',
                    ],
                ],
            ], 422);
        }

        $estudianteValor = $datos['estudiante_id'] ?? null;

        if ($estudianteValor !== null && ! is_int($estudianteValor)) {
            return response()->json([
                'message' => 'Estudiante inválido.',
            ], 422);
        }

        $estudianteId = $estudianteValor;

        $codigoValor = $datos['codigo_universitario'] ?? null;

        if ($codigoValor !== null) {
            if (! is_string($codigoValor)) {
                return response()->json([
                    'message' => 'Código universitario inválido.',
                ], 422);
            }

            $estudianteId = $incidencias->estudiantePorCodigoEnExamen(
                $examenId,
                $codigoValor,
            );

            if ($estudianteId === null) {
                return response()->json([
                    'message' => 'El código no corresponde a un estudiante del examen.',
                    'errors' => [
                        'codigo_universitario' => [
                            'No se encontró un estudiante del examen con ese código.',
                        ],
                    ],
                ], 422);
            }
        }

        if (
            $estudianteId !== null
            && ! $incidencias->estudiantePerteneceAlExamen(
                $examenId,
                $estudianteId,
            )
        ) {
            return response()->json([
                'message' => 'El estudiante no pertenece al examen seleccionado.',
                'errors' => [
                    'estudiante_id' => [
                        'El estudiante no pertenece al examen seleccionado.',
                    ],
                ],
            ], 422);
        }

        $id = $incidencias->registrar(
            $examenId,
            $aula,
            $estudianteId,
            $usuarioId,
            $tipo,
            $detalle,
        );

        return response()->json([
            'message' => 'Incidencia registrada.',
            'data' => [
                'incidencia_id' => $id,
                'examen_id' => $examenId,
                'aula_id' => $aula,
                'tipo' => $tipo,
                'detalle' => $detalle,
                'estudiante_id' => $estudianteId,
            ],
        ], 201);
    }
}
