<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Controllers;

use App\Modules\Habilitacion\Application\Actions\ConsultarHabilitacion;
use App\Modules\Habilitacion\Application\DTOs\ConsultaHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\ExamenHabilitacionData;
use App\Modules\Habilitacion\Http\Requests\ConsultarHabilitacionRequest;
use Illuminate\Http\JsonResponse;

/**
 * Consulta rápida de habilitación (HU-13). Es de solo lectura y la usa el
 * personal de control, que no tiene por qué poder modificar la lista.
 */
final class ConsultaHabilitacionController
{
    public function examenes(ConsultarHabilitacion $consultar): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (ExamenHabilitacionData $examen): array => $examen->toArray(),
                $consultar->listarExamenes(),
            ),
        ]);
    }

    public function consultar(
        ConsultarHabilitacionRequest $request,
        int $examen,
        ConsultarHabilitacion $consultar,
    ): JsonResponse {
        abort_unless($consultar->existeExamen($examen), 404, 'El examen no existe.');

        $coincidencias = $consultar->porIdentificador($examen, $request->identificador());

        abort_if(
            $coincidencias === [],
            404,
            'Ningún estudiante activo del padrón tiene ese código universitario o documento.',
        );

        return response()->json([
            'data' => array_map(
                static fn (ConsultaHabilitacionData $consulta): array => $consulta->toArray(),
                $coincidencias,
            ),
        ]);
    }
}
