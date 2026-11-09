<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Controllers;

use App\Modules\Reportes\Application\DTOs\FilaMiExamen;
use App\Modules\Reportes\Application\Queries\ArmarListaDeGrupo;
use App\Modules\Reportes\Application\Queries\ArmarTabla;
use App\Modules\Reportes\Application\Queries\ConsultarBitacoraExamen;
use App\Modules\Reportes\Application\Queries\ExportarTabla;
use App\Modules\Reportes\Application\Queries\ListarMisExamenes;
use App\Modules\Reportes\Domain\Exceptions\ExamenSinRendirException;
use App\Modules\Reportes\Domain\Exceptions\RecursoAjenoException;
use App\Modules\Reportes\Http\Requests\ReporteExamenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Reportes del docente: sus examenes con la asistencia, las descargas de
 * un examen suyo (asistencia, ausentes e incidencias) y la lista de inscritos de un grupo suyo. Solo lectura.
 */
final class ReporteDocenteController
{
    use EntregaArchivo;
    use RespondeBitacora;

    public function misExamenes(
        ReporteExamenRequest $request,
        ListarMisExamenes $listar,
    ): JsonResponse {
        $pagina = $request->pagina();
        $porPagina = $request->porPagina();

        $resultado = $listar->execute(
            $this->usuarioId(),
            $request->periodoId(),
            $request->asignaturaId(),
            $pagina,
            $porPagina,
        );

        return response()->json([
            'data' => array_map(
                // Sin rendir, el examen no tiene cifras de asistencia.
                static fn (FilaMiExamen $fila): array => [
                    'id' => $fila->id,
                    'asignatura' => [
                        'id' => $fila->asignaturaId,
                        'nombre' => $fila->asignatura,
                    ],
                    'tipo' => $fila->tipo,
                    'fecha' => $fila->fecha,
                    'hora' => $fila->hora,
                    'rendido' => $fila->rendido,
                    'presentes' => $fila->rendido ? $fila->presentes : null,
                    'habilitados' => $fila->habilitados,
                    'no_habilitados' => $fila->noHabilitados,
                    'ausentes' => $fila->rendido ? $fila->ausentes : null,
                    'asistencia' => $fila->rendido ? $fila->asistencia() : null,
                    'incidencias' => $fila->rendido ? $fila->incidencias : null,
                ],
                $resultado['filas'],
            ),
            'meta' => [
                'total' => $resultado['total'],
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'examenes' => $resultado['examenes'],
                'rendidos' => $resultado['rendidos'],
                'asignaturas' => $resultado['asignaturas'],
            ],
        ]);
    }

    public function exportar(
        ReporteExamenRequest $request,
        string $reporte,
        ArmarTabla $armar,
        ExportarTabla $exportar,
    ): JsonResponse|Response {
        try {
            $tabla = $armar->deExamen($reporte, $request->examenId(), $this->usuarioId());
        } catch (RecursoAjenoException $ajeno) {
            return $this->ajeno($ajeno);
        } catch (ExamenSinRendirException $sinRendir) {
            return response()->json([
                'message' => $sinRendir->getMessage(),
                'codigo' => 'SIN_RENDIR',
            ], Response::HTTP_CONFLICT);
        }

        if ($tabla === null) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->descarga($exportar->execute($tabla, $request->formato()));
    }

    public function bitacora(int $examen, ConsultarBitacoraExamen $consultar): JsonResponse
    {
        try {
            $sucesos = $consultar->execute($examen, $this->usuarioId());
        } catch (RecursoAjenoException $ajeno) {
            return $this->ajeno($ajeno);
        }

        return $this->bitacoraJson($sucesos);
    }

    public function estudiantesDeGrupo(
        ReporteExamenRequest $request,
        int $grupo,
        ArmarListaDeGrupo $armar,
        ExportarTabla $exportar,
    ): JsonResponse|Response {
        try {
            $tabla = $armar->execute($grupo, $this->usuarioId());
        } catch (RecursoAjenoException $ajeno) {
            return $this->ajeno($ajeno);
        }

        if ($tabla === null) {
            return response()->json([
                'message' => 'Grupo no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->descarga($exportar->execute($tabla, ExportarTabla::XLSX));
    }

    private function ajeno(RecursoAjenoException $ajeno): JsonResponse
    {
        return response()->json([
            'message' => $ajeno->getMessage(),
            'alcance' => true,
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Las rutas llevan `auth`: aqui siempre hay una cuenta.
     */
    private function usuarioId(): int
    {
        $id = Auth::id();

        return is_int($id) ? $id : 0;
    }
}
