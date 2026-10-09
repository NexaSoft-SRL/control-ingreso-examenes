<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Controllers;

use App\Modules\Habilitacion\Application\Actions\RepartirPorAula;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\AulaRepartoData;
use App\Modules\Habilitacion\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Habilitacion\Domain\Exceptions\ExamenSinAulasException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class RepartoController
{
    public function store(
        int $examen,
        RepartirPorAula $repartir,
        HabilitacionGateway $habilitaciones,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $habilitaciones->existeExamen($examen)) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $reparto = $repartir->execute($examen, $usuarioId);
        } catch (ExamenAjenoException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'alcance' => true,
            ], Response::HTTP_FORBIDDEN);
        } catch (ExamenSinAulasException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
                'codigo' => 'SIN_AULAS',
            ], Response::HTTP_CONFLICT);
        }

        $mensaje = $reparto->repartidos === 0
            ? 'Sin estudiantes por repartir.'
            : sprintf(
                '%d %s en %d %s.',
                $reparto->repartidos,
                $reparto->repartidos === 1 ? 'estudiante' : 'estudiantes',
                $reparto->aulasUsadas,
                $reparto->aulasUsadas === 1 ? 'aula' : 'aulas',
            );

        return response()->json([
            'message' => $mensaje,
            'repartidos' => $reparto->repartidos,
            'por_aula' => array_map(
                static fn (AulaRepartoData $aula): array => [
                    'aula_id' => $aula->aulaId,
                    'nombre' => $aula->nombre,
                    'asignados' => $aula->asignados,
                ],
                $reparto->porAula,
            ),
        ]);
    }
}
