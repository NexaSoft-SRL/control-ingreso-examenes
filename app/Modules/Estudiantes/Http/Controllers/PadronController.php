<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Estudiantes\Application\Actions\CorregirDatosEstudiante;
use App\Modules\Estudiantes\Application\Queries\ConsultarFichaDeEstudiante;
use App\Modules\Estudiantes\Application\Queries\ConsultarResumenPadron;
use App\Modules\Estudiantes\Application\Queries\ListarEstudiantes;
use App\Modules\Estudiantes\Http\Requests\CorregirDatosEstudianteRequest;
use App\Modules\Estudiantes\Http\Requests\ListaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * El padron de la universidad (rutas 34 y 35) y la ficha de un estudiante,
 * incluida la correccion auditada de sus datos identificativos.
 */
final class PadronController
{
    public function index(ListaRequest $request, ListarEstudiantes $listar): JsonResponse
    {
        $pagina = $listar->execute($request->toData());

        return response()->json([
            'data' => $pagina->filas,
            'meta' => $pagina->meta(),
        ]);
    }

    public function resumen(ConsultarResumenPadron $consultar): JsonResponse
    {
        return response()->json($consultar->execute());
    }

    public function ficha(int $estudiante, ConsultarFichaDeEstudiante $consultar): JsonResponse
    {
        $ficha = $consultar->execute($estudiante);

        if ($ficha === null) {
            return response()->json([
                'message' => 'Estudiante no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $ficha]);
    }

    public function corregir(
        int $estudiante,
        CorregirDatosEstudianteRequest $request,
        CorregirDatosEstudiante $corregir,
        ConsultarFichaDeEstudiante $consultar,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json(['message' => 'No hay una sesión activa.'], Response::HTTP_UNAUTHORIZED);
        }

        $datos = $request->validated();

        $nombres = $datos['nombres'] ?? null;
        $apellidos = $datos['apellidos'] ?? null;
        $documento = $datos['documento'] ?? null;

        if (
            ! is_string($nombres)
            || ! is_string($apellidos)
            || ($documento !== null && ! is_string($documento))
        ) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $corregir->execute(
            $usuarioId,
            $estudiante,
            $nombres,
            $apellidos,
            $documento,
        )) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $consultar->execute($estudiante),
            'message' => 'Datos del estudiante actualizados.',
        ]);
    }
}
