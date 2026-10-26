<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\IncidenciaManualPuertaGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Domain\Enums\TipoIncidenciaPuerta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class RegistrarIncidenciaManualPuertaController
{
    public function __invoke(
        Request $request,
        int $aula,
        ExamenGateway $examenes,
        AlcancePuntoControl $alcance,
        IncidenciaManualPuertaGateway $incidencias,
        VentanaPuntoControl $ventana,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        if ($aula <= 0) {
            return response()->json([
                'message' => 'Aula no válida.',
            ], 422);
        }

        $datos = $request->validate([
            'examen_id' => ['required', 'integer', 'min:1'],
            // N5: catalogo cerrado; las situaciones excepcionales van por
            // su propia ruta (admiten o no segun el tipo).
            'tipo' => ['required', 'string', Rule::in(TipoIncidenciaPuerta::comunes())],
            'detalle' => ['required', 'string', 'min:5', 'max:500'],
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
        ], [
            'examen_id.required' => 'Obligatorio',
            'tipo.required' => 'Obligatorio',
            'tipo.in' => 'Tipo no válido',
            'detalle.required' => 'Obligatorio',
            'detalle.min' => 'Mínimo 5 caracteres',
            'detalle.max' => 'Máximo 500 caracteres',
            'codigo_universitario.regex' => 'Código SIS no válido',
        ]);

        if (! is_array($datos)) {
            return response()->json([
                'message' => 'Datos de incidencia no válidos.',
            ], 422);
        }

        $examenIdValor = $datos['examen_id'] ?? null;

        if (! is_int($examenIdValor)) {
            return response()->json([
                'message' => 'Examen no válido.',
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
                'message' => 'Sin asignación en esta aula.',
            ], 403);
        }

        // N3: solo durante la ventana del examen.
        if (! $ventana->estaAbierta($examenId)) {
            return response()->json([
                'message' => 'Fuera del horario del examen.',
            ], 409);
        }

        $tipoValor = $datos['tipo'] ?? null;
        $detalleValor = $datos['detalle'] ?? null;

        if (! is_string($tipoValor) || ! is_string($detalleValor)) {
            return response()->json([
                'message' => 'Tipo o detalle no válidos.',
            ], 422);
        }

        $tipo = trim($tipoValor);
        $detalle = trim($detalleValor);

        if (
            mb_strlen($tipo) < 3
            || mb_strlen($detalle) < 5
        ) {
            return response()->json([
                'message' => 'Detalle no válido.',
                'errors' => [
                    'detalle' => [
                        'Mínimo 5 caracteres',
                    ],
                ],
            ], 422);
        }

        $estudianteValor = $datos['estudiante_id'] ?? null;

        if ($estudianteValor !== null && ! is_int($estudianteValor)) {
            return response()->json([
                'message' => 'Estudiante no válido.',
            ], 422);
        }

        $estudianteId = $estudianteValor;

        $codigoValor = $datos['codigo_universitario'] ?? null;

        if ($codigoValor !== null) {
            if (! is_string($codigoValor)) {
                return response()->json([
                    'message' => 'Código universitario no válido.',
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
                'tipo_etiqueta' => TipoIncidenciaPuerta::tryFrom($tipo)?->etiqueta() ?? $tipo,
                'detalle' => $detalle,
                'estudiante_id' => $estudianteId,
            ],
        ], 201);
    }
}
