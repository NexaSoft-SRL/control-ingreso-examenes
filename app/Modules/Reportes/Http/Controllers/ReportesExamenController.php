<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Controllers;

use App\Modules\Reportes\Application\Contracts\ConsultaReportesGateway;
use App\Modules\Reportes\Application\Contracts\GeneradorArchivoReporte;
use App\Modules\Reportes\Application\Queries\ListarReportesDocente;
use App\Modules\Reportes\Http\Requests\ListarIncidenciasReportesRequest;
use App\Modules\Reportes\Http\Requests\ListarReportesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class ReportesExamenController
{
    public function index(
        ListarReportesRequest $request,
        ListarReportesDocente $listar,
    ): JsonResponse {
        try {
            $resultado = $listar->execute(
                (int) Auth::id(),
                $request->filled('periodo') ? $request->integer('periodo') : null,
                $request->filled('asignatura') ? $request->integer('asignatura') : null,
                $request->integer('pagina', 1),
            );
        } catch (InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($resultado);
    }

    public function show(int $examen, ConsultaReportesGateway $reportes): JsonResponse
    {
        $error = $this->comprobarAlcance($examen, $reportes);

        if ($error !== null) {
            return $error;
        }

        $reporte = $reportes->reporte($examen);

        if ($reporte === null) {
            return response()->json(['message' => 'Examen no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        unset($reporte['filas']);
        $reporte['muestras']['incidencias'] = [];

        return response()->json(['data' => $reporte]);
    }

    public function incidencias(
        ListarIncidenciasReportesRequest $request,
        int $examen,
        ConsultaReportesGateway $reportes,
    ): JsonResponse {
        $error = $this->comprobarAlcance($examen, $reportes);

        if ($error !== null) {
            return $error;
        }

        $detalle = $reportes->reporte($examen);

        if ($detalle === null) {
            return response()->json(['message' => 'Examen no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if (! $detalle['rendido']) {
            return response()->json([
                'codigo' => 'EXAMEN_NO_INICIADO',
                'message' => 'El examen todavía no se rindió.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json($reportes->incidencias($examen, $request->integer('pagina', 1)));
    }

    public function descargar(
        int $examen,
        string $tipo,
        string $formato,
        ConsultaReportesGateway $reportes,
        GeneradorArchivoReporte $generador,
    ): Response|JsonResponse {
        $error = $this->comprobarAlcance($examen, $reportes);

        if ($error !== null) {
            return $error;
        }

        if (! in_array($tipo, ['asistencia', 'ausentes', 'incidencias'], true)) {
            return response()->json(['message' => 'Reporte no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if (! in_array($formato, ['pdf', 'xlsx'], true)) {
            return response()->json(['message' => 'Formato no válido.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reporte = $reportes->reporte($examen);

        if ($reporte === null) {
            return response()->json(['message' => 'Examen no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if (! $reporte['rendido']) {
            return response()->json([
                'codigo' => 'EXAMEN_NO_INICIADO',
                'message' => 'El examen todavía no se rindió.',
            ], Response::HTTP_CONFLICT);
        }

        $archivo = $generador->generar($reporte, $tipo, $formato);

        return response($archivo->contenido, Response::HTTP_OK, [
            'Content-Type' => $archivo->tipoContenido,
            'Content-Disposition' => 'attachment; filename="'.$archivo->nombre.'"',
            'Content-Length' => (string) strlen($archivo->contenido),
        ]);
    }

    private function comprobarAlcance(int $examenId, ConsultaReportesGateway $reportes): ?JsonResponse
    {
        if (! $reportes->existe($examenId)) {
            return response()->json(['message' => 'Examen no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if (! $reportes->perteneceAlDocente($examenId, (int) Auth::id())) {
            return response()->json(['message' => 'Este examen no está a su cargo.'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
