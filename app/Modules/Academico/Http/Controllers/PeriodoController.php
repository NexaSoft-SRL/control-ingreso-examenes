<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Application\Actions\AjustarPeriodo;
use App\Modules\Academico\Application\Actions\DetectarPeriodos;
use App\Modules\Academico\Application\Queries\ConsultarPeriodosVigentes;
use App\Modules\Academico\Application\Queries\ConsultarResumenPeriodo;
use App\Modules\Academico\Application\Queries\ListarPeriodos;
use App\Modules\Academico\Http\Requests\AjustarPeriodoRequest;
use App\Modules\Academico\Http\Requests\PaginaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Rutas 19 a 23: periodos detectados, vigentes, ajuste de fechas,
 * deteccion y resumen de la pantalla Periodo.
 */
final class PeriodoController
{
    public function index(PaginaRequest $request, ListarPeriodos $listar): JsonResponse
    {
        return response()->json($listar->execute($request->pagina(4)));
    }

    public function vigentes(ConsultarPeriodosVigentes $consultar): JsonResponse
    {
        return response()->json($consultar->execute());
    }

    public function resumen(ConsultarResumenPeriodo $consultar): JsonResponse
    {
        return response()->json($consultar->execute());
    }

    public function ajustar(int $periodo, AjustarPeriodoRequest $request, AjustarPeriodo $ajustar): JsonResponse
    {
        $autorId = Auth::id();

        $fila = $ajustar->execute(
            $periodo,
            $request->fechaInicio(),
            $request->fechaFin(),
            is_int($autorId) ? $autorId : null,
        );

        if ($fila === null) {
            return response()->json([
                'message' => 'Período no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $fila]);
    }

    public function detectar(DetectarPeriodos $detectar): JsonResponse
    {
        $resultado = $detectar->execute();

        return response()->json([
            'creados' => $resultado->creados,
            'actualizados' => $resultado->actualizados,
        ]);
    }
}
