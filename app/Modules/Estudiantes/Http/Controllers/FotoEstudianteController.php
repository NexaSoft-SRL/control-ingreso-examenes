<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Estudiantes\Application\Actions\ReemplazarFoto;
use App\Modules\Estudiantes\Application\Contracts\FotosDeEstudiantes;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Domain\Exceptions\ImagenNoValidaException;
use App\Modules\Estudiantes\Http\Requests\FotoRequest;
use App\Modules\Estudiantes\Http\Requests\PlazoFotoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * La administracion: la foto de cada estudiante y el plazo en que los
 * estudiantes suben la suya.
 */
final class FotoEstudianteController
{
    public function ver(int $estudiante, PortalEstudianteGateway $portal, FotosDeEstudiantes $fotos): JsonResponse|Response
    {
        if ($portal->estudiante($estudiante) === null) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $jpeg = $fotos->foto($estudiante);

        if ($jpeg === null) {
            return response()->json(['message' => 'Sin foto.'], Response::HTTP_NOT_FOUND);
        }

        return response($jpeg, Response::HTTP_OK, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function reemplazar(
        int $estudiante,
        FotoRequest $request,
        PortalEstudianteGateway $portal,
        ReemplazarFoto $reemplazar,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if ($portal->estudiante($estudiante) === null || ! is_int($usuarioId)) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $reemplazar->execute($estudiante, $request->imagen(), $usuarioId);
        } catch (ImagenNoValidaException $error) {
            return response()->json([
                'message' => $error->getMessage(),
                'errors' => ['foto' => ['Imagen no válida']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['message' => 'Foto guardada.']);
    }

    public function plazo(PortalEstudianteGateway $portal): JsonResponse
    {
        $plazo = $portal->plazo();

        return response()->json([
            'data' => $plazo === null ? null : [
                'desde' => $plazo->desde,
                'hasta' => $plazo->hasta,
                'abierto' => $plazo->abiertoEl(now()->toDateString()),
                'estado' => $plazo->estadoEl(now()->toDateString()),
            ],
        ]);
    }

    public function fijarPlazo(
        PlazoFotoRequest $request,
        PortalEstudianteGateway $portal,
        BitacoraGateway $bitacora,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json(['message' => 'No hay una sesión activa.'], Response::HTTP_UNAUTHORIZED);
        }

        $plazo = $request->toData();
        $vigentes = $portal->fechasVigentes();

        if ($vigentes !== null && ($plazo->desde < $vigentes['inicio'] || $plazo->hasta > $vigentes['fin'])) {
            $fuera = sprintf('Fuera del período: %s – %s', $this->fecha($vigentes['inicio']), $this->fecha($vigentes['fin']));

            return response()->json([
                'message' => $fuera.'.',
                'errors' => $plazo->desde < $vigentes['inicio'] ? ['desde' => [$fuera]] : ['hasta' => [$fuera]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $portal->fijarPlazo($plazo, $usuarioId);
        $bitacora->registrar(
            $usuarioId,
            'estudiante.plazo_fotos',
            'plazos_foto',
            null,
            sprintf('Plazo de fotos del %s al %s.', $this->fecha($plazo->desde), $this->fecha($plazo->hasta)),
        );

        return response()->json([
            'data' => [
                'desde' => $plazo->desde,
                'hasta' => $plazo->hasta,
                'abierto' => $plazo->abiertoEl(now()->toDateString()),
                'estado' => $plazo->estadoEl(now()->toDateString()),
            ],
            'message' => 'Plazo guardado.',
        ]);
    }

    // '2026-10-12' → '12 oct 2026'.
    private function fecha(string $dia): string
    {
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        [$anio, $mes, $numero] = array_map(intval(...), explode('-', $dia) + [0, 0, 0]);

        return sprintf('%d %s %d', $numero, $meses[$mes - 1] ?? '', $anio);
    }
}
