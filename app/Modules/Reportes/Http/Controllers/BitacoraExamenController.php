<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Controllers;

use App\Modules\Administracion\Application\Contracts\ConsultaPermisosGateway;
use App\Modules\Reportes\Application\Contracts\ConsultaBitacoraExamenGateway;
use App\Modules\Reportes\Application\Contracts\ConsultaReportesGateway;
use App\Modules\Reportes\Application\Contracts\GeneradorArchivoBitacora;
use App\Modules\Reportes\Http\Requests\ListarBitacoraExamenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class BitacoraExamenController
{
    public function examenes(
        ConsultaBitacoraExamenGateway $bitacora,
        ConsultaPermisosGateway $permisos,
    ): JsonResponse {
        return response()->json([
            'data' => $bitacora->examenesVisibles(
                $this->usuarioId(),
                $permisos->tienePermiso($this->usuarioId(), 'reportes_universidad'),
            ),
        ]);
    }

    public function index(
        ListarBitacoraExamenRequest $request,
        int $examen,
        ConsultaBitacoraExamenGateway $bitacora,
        ConsultaReportesGateway $reportes,
        ConsultaPermisosGateway $permisos,
    ): JsonResponse {
        $error = $this->comprobarAlcance($examen, $bitacora, $reportes, $permisos);

        if ($error !== null) {
            return $error;
        }

        $datosExamen = $bitacora->examen($examen);

        if ($datosExamen === null) {
            return response()->json(['message' => 'Examen no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $resultado = $bitacora->listar(
            $examen,
            $request->categoria(),
            $request->buscar(),
            $request->aula(),
            $request->pagina(),
        );

        return response()->json([
            'data' => $resultado['data'],
            'examen' => $datosExamen,
            'aulas' => $bitacora->aulas($examen),
            'categorias' => $bitacora->categorias($examen),
            'meta' => $resultado['meta'],
        ]);
    }

    public function descargar(
        int $examen,
        string $formato,
        ConsultaBitacoraExamenGateway $bitacora,
        ConsultaReportesGateway $reportes,
        GeneradorArchivoBitacora $generador,
        ConsultaPermisosGateway $permisos,
    ): Response|JsonResponse {
        $error = $this->comprobarAlcance($examen, $bitacora, $reportes, $permisos);

        if ($error !== null) {
            return $error;
        }

        if (! in_array($formato, ['pdf', 'xlsx'], true)) {
            return response()->json(['message' => 'Formato no válido.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $datosExamen = $bitacora->examen($examen);

        if ($datosExamen === null) {
            return response()->json(['message' => 'Examen no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $eventos = $bitacora->todos($examen);

        if ($eventos === []) {
            return response()->json([
                'message' => 'No hay eventos para descargar.',
            ], Response::HTTP_CONFLICT);
        }

        $archivo = $generador->generar($datosExamen, $eventos, $formato);
        $nombreUtf8 = rawurlencode($archivo->nombre);
        $nombreFallback = sprintf('bitacora %s.%s', $datosExamen['fecha'], $formato);

        return response($archivo->contenido, Response::HTTP_OK, [
            'Content-Type' => $archivo->tipoContenido,
            'Content-Disposition' => sprintf(
                'attachment; filename="%s"; filename*=UTF-8\'\'%s',
                $nombreFallback,
                $nombreUtf8,
            ),
            'Content-Length' => (string) strlen($archivo->contenido),
        ]);
    }

    private function comprobarAlcance(
        int $examenId,
        ConsultaBitacoraExamenGateway $bitacora,
        ConsultaReportesGateway $reportes,
        ConsultaPermisosGateway $permisos,
    ): ?JsonResponse {
        if (! $bitacora->existe($examenId)) {
            return response()->json(['message' => 'Examen no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($permisos->tienePermiso($this->usuarioId(), 'reportes_universidad')) {
            return null;
        }

        if (! $reportes->perteneceAlDocente($examenId, $this->usuarioId())) {
            return response()->json([
                'message' => 'Este examen no está a su cargo.',
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function usuarioId(): int
    {
        $id = Auth::id();

        return is_int($id) || (is_string($id) && ctype_digit($id))
            ? (int) $id
            : 0;
    }
}
