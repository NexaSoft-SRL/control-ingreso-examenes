<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Estudiantes\Application\Actions\EditarEstudiante;
use App\Modules\Estudiantes\Application\DTOs\EdicionDeEstudianteData;
use App\Modules\Estudiantes\Application\Queries\ConsultarFichaDeEstudiante;
use App\Modules\Estudiantes\Application\Queries\ConsultarResumenPadron;
use App\Modules\Estudiantes\Application\Queries\ListarEstudiantes;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;
use App\Modules\Estudiantes\Http\Requests\EditarEstudianteRequest;
use App\Modules\Estudiantes\Http\Requests\ListaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * El padron de la universidad (rutas 34 y 35) y la ficha de un estudiante.
 * Solo lectura: se llena con cargas y se corrige resolviendo conflictos.
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

    public function editar(
        EditarEstudianteRequest $request,
        int $estudiante,
        EditarEstudiante $editar,
    ): JsonResponse {
        try {
            $editado = $editar->execute(new EdicionDeEstudianteData(
                usuarioId: (int) Auth::id(),
                estudianteId: $estudiante,
                documento: $request->documento(),
                nombres: $request->nombres(),
                apellidos: $request->apellidos(),
            ));
        } catch (DocumentoEnUsoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => 'DOCUMENTO_EN_USO',
                'errors' => ['documento' => [$excepcion->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $editado) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Estudiante actualizado.']);
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
}
