<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Controllers;

use App\Modules\Reportes\Application\Queries\ConsultarReporte;
use App\Modules\Reportes\Application\Queries\ListarExamenesParaReporte;
use App\Modules\Reportes\Http\Requests\AlcanceReporteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ReportesUniversidadController
{
    public function index(
        AlcanceReporteRequest $request,
        ListarExamenesParaReporte $listar,
    ): JsonResponse {
        $resultado = $listar->execute(
            $request->toAlcance(),
            $request->pagina(),
            $request->porPagina(),
        );

        $total = $resultado['total'];
        $porPagina = $request->porPagina();

        return response()->json([
            'data' => $resultado['filas'],
            'conteos' => $resultado['conteos'],
            'cifras' => $resultado['cifras'],
            'meta' => [
                'total' => $total,
                'pagina' => $request->pagina(),
                'por_pagina' => $porPagina,
                'paginas' => (int) ceil($total / $porPagina),
            ],
        ]);
    }

    public function show(
        AlcanceReporteRequest $request,
        string $tipo,
        ConsultarReporte $consultar,
    ): JsonResponse {
        $alcance = $request->toAlcance();

        if (in_array($tipo, ['incidencias', 'asistencia', 'ausentes'], true)) {
            $examenId = $alcance->examenId;

            if ($examenId === null) {
                return response()->json([
                    'message' => 'Debe seleccionar un examen.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($consultar->examen($examenId) === null) {
                return response()->json([
                    'message' => 'Examen no encontrado.',
                ], Response::HTTP_NOT_FOUND);
            }

            if ($tipo === 'incidencias') {
                $datos = $consultar->incidenciasDeExamen($examenId);
            } elseif ($tipo === 'asistencia') {
                $datos = $consultar->asistencia($examenId);
            } else {
                $datos = $consultar->ausentes($examenId);
            }

            return response()->json([
                'data' => $datos,
                'alcance' => $consultar->nombresDelAlcance($alcance),
            ]);
        }

        $datos = match ($tipo) {
            'horarios' => $consultar->horarios($alcance),
            'registrados' => $consultar->registrados($alcance),
            'intentos' => $consultar->intentos($alcance),
            'estudiantes' => $consultar->resumenPorEstudiante($alcance),
            default => null,
        };

        if ($datos === null) {
            return response()->json([
                'message' => 'Tipo de reporte no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $datos,
            'alcance' => $consultar->nombresDelAlcance($alcance),
        ]);
    }
}
