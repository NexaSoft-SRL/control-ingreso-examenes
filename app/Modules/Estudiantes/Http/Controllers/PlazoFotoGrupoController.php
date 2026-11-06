<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Controllers;

use App\Modules\Estudiantes\Application\Actions\FijarPlazoFotoDeGrupo;
use App\Modules\Estudiantes\Application\DTOs\PlazoFotoDeGrupoData;
use App\Modules\Estudiantes\Application\Queries\ConsultarPlazoFotoDeGrupo;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Http\Requests\PlazoFotoDeGrupoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * El plazo de fotos de un grupo del docente: lo ve con cuantos faltan, lo
 * fija, lo mueve o lo quita.
 */
final class PlazoFotoGrupoController
{
    public function ver(int $grupo, ConsultarPlazoFotoDeGrupo $consultar): JsonResponse
    {
        try {
            return $this->responder($consultar->execute((int) Auth::id(), $grupo));
        } catch (GrupoAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        }
    }

    public function fijar(PlazoFotoDeGrupoRequest $request, int $grupo, FijarPlazoFotoDeGrupo $fijar): JsonResponse
    {
        try {
            return $this->responder(
                $fijar->execute((int) Auth::id(), $grupo, $request->hasta(), now()->toDateString()),
            );
        } catch (GrupoAjenoException $excepcion) {
            return $this->ajeno($excepcion);
        }
    }

    private function responder(?PlazoFotoDeGrupoData $plazo): JsonResponse
    {
        if ($plazo === null) {
            return response()->json(['message' => 'Grupo no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $plazo->comoArreglo(now()->toDateString())]);
    }

    private function ajeno(GrupoAjenoException $excepcion): JsonResponse
    {
        return response()->json([
            'message' => $excepcion->getMessage(),
            'alcance' => true,
        ], Response::HTTP_FORBIDDEN);
    }
}
