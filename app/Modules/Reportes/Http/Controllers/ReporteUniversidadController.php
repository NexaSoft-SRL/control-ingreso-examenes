<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Controllers;

use App\Modules\Reportes\Application\DTOs\FilaExamenReporte;
use App\Modules\Reportes\Application\Queries\ArmarTabla;
use App\Modules\Reportes\Application\Queries\ConsultarBitacoraExamen;
use App\Modules\Reportes\Application\Queries\ExportarTabla;
use App\Modules\Reportes\Application\Queries\ListarExamenesParaReporte;
use App\Modules\Reportes\Domain\Exceptions\ExamenSinRendirException;
use App\Modules\Reportes\Domain\Exceptions\ReporteSinDatosException;
use App\Modules\Reportes\Http\Requests\AlcanceReporteRequest;
use App\Modules\Reportes\Http\Requests\ReporteExamenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Reportes de toda la universidad, del administrador: el listado de
 * examenes con sus cifras y las tres descargas. Solo lectura.
 */
final class ReporteUniversidadController
{
    use EntregaArchivo;
    use RespondeBitacora;

    public function examenes(
        AlcanceReporteRequest $request,
        ListarExamenesParaReporte $listar,
    ): JsonResponse {
        $pagina = $request->pagina();
        $porPagina = $request->porPagina();

        $resultado = $listar->execute($request->toAlcance(), $pagina, $porPagina);

        return response()->json([
            'data' => array_map(
                static fn (FilaExamenReporte $fila): array => [
                    'id' => $fila->id,
                    'asignatura' => $fila->asignatura,
                    'tipo' => $fila->tipo,
                    'docente' => $fila->docente,
                    'facultad' => $fila->facultad,
                    'fecha' => $fila->fecha,
                    'hora' => $fila->hora,
                    'aulas' => $fila->aulas,
                    'ingresados' => $fila->ingresados,
                    'habilitados' => $fila->habilitados,
                ],
                $resultado['filas'],
            ),
            'meta' => [
                'total' => $resultado['total'],
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'conteos' => $resultado['conteos'],
                'cifras' => $resultado['cifras'],
            ],
        ]);
    }

    public function bitacora(int $examen, ConsultarBitacoraExamen $consultar): JsonResponse
    {
        return $this->bitacoraJson($consultar->execute($examen, 0, deLaUniversidad: true));
    }

    public function exportarBitacora(
        ReporteExamenRequest $request,
        ArmarTabla $armar,
        ExportarTabla $exportar,
    ): JsonResponse|Response {
        try {
            $tabla = $armar->deExamen('bitacora', $request->examenId(), 0, deLaUniversidad: true);
        } catch (ExamenSinRendirException $sinRendir) {
            return response()->json([
                'message' => $sinRendir->getMessage(),
                'codigo' => 'SIN_RENDIR',
            ], Response::HTTP_CONFLICT);
        }

        if ($tabla === null) {
            return response()->json(['message' => 'Examen no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->descarga($exportar->execute($tabla, $request->formato()));
    }

    public function exportar(
        AlcanceReporteRequest $request,
        string $reporte,
        ArmarTabla $armar,
        ExportarTabla $exportar,
    ): JsonResponse|Response {
        try {
            $tabla = $armar->execute($reporte, $request->toAlcance());
        } catch (ReporteSinDatosException $sinDatos) {
            return response()->json([
                'message' => $sinDatos->getMessage(),
                'codigo' => 'SIN_DATOS',
            ], Response::HTTP_CONFLICT);
        }

        return $this->descarga($exportar->execute($tabla, $request->formato()));
    }
}
