<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Actions\AsignarAmbienteAExamen;
use App\Modules\Examenes\Application\Actions\ConsultarAmbientesDeExamen;
use App\Modules\Examenes\Application\Actions\QuitarAmbienteDeExamen;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\AmbienteAsignadoData;
use App\Modules\Examenes\Domain\Exceptions\AmbienteNoDisponibleException;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Http\Requests\AsignarAmbienteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class AmbienteExamenController
{
    public function index(
        int $examen,
        ConsultarAmbientesDeExamen $consultar,
        ExamenGateway $examenes,
    ): JsonResponse {
        if (! $examenes->buscar($examen) instanceof Examen) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        $ocupacion = $consultar->ocupacion($examen);

        return response()->json([
            'data' => array_map(
                fn (AmbienteAsignadoData $ambiente): array => $this->serializar($ambiente),
                $consultar->execute($examen),
            ),
            'ocupacion' => [
                'capacidad_asignada' => $ocupacion->capacidadAsignada,
                'habilitados' => $ocupacion->habilitados,
                'alcanza' => $ocupacion->alcanza(),
            ],
        ]);
    }

    public function store(
        AsignarAmbienteRequest $request,
        int $examen,
        AsignarAmbienteAExamen $asignar,
        ExamenGateway $examenes,
    ): JsonResponse {
        if (! $examenes->buscar($examen) instanceof Examen) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var array{ambiente_id: int} $datos */
        $datos = $request->validated();

        try {
            $ambiente = $asignar->execute(
                $examen,
                $datos['ambiente_id'],
                $this->usuarioId(),
            );
        } catch (AmbienteNoDisponibleException) {
            return response()->json([
                'message' => 'El ambiente no está disponible: está en mantenimiento o ya tiene otro examen a esa hora.',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'data' => $this->serializar($ambiente),
        ], Response::HTTP_CREATED);
    }

    public function destroy(
        int $examen,
        int $ambiente,
        QuitarAmbienteDeExamen $quitar,
    ): JsonResponse|Response {
        if (! $quitar->execute($examen, $ambiente, $this->usuarioId())) {
            return response()->json([
                'message' => 'Ese ambiente no está asignado al examen.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(AmbienteAsignadoData $ambiente): array
    {
        return [
            'id' => $ambiente->id,
            'ambiente_id' => $ambiente->ambienteId,
            'nombre' => $ambiente->nombre,
            'ubicacion' => $ambiente->ubicacion,
            'capacidad' => $ambiente->capacidad,
            'estado' => $ambiente->estado,
        ];
    }

    private function usuarioId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }
}
