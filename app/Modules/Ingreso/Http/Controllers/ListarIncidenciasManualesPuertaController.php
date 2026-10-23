<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\IncidenciaManualPuertaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ListarIncidenciasManualesPuertaController
{
    public function __invoke(
        Request $request,
        int $aula,
        ExamenGateway $examenes,
        AlcancePuntoControl $alcance,
        IncidenciaManualPuertaGateway $incidencias,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json(['message' => 'Sin sesión activa.'], 401);
        }

        $request->validate([
            'examen_id' => ['required', 'integer', 'min:1'],
            'limite' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        if ($aula <= 0) {
            return response()->json(['message' => 'Aula inválida.'], 422);
        }

        $examenId = $request->integer('examen_id');
        $limite = $request->integer('limite', 20);
        $examen = $examenes->actual($examenId);

        if ($examen === null || ! in_array($aula, $examen->aulas, true)) {
            return response()->json(['message' => 'Examen o aula no encontrados.'], 404);
        }

        if (! $alcance->puedeOperar($usuarioId, $examenId, $aula)) {
            return response()->json(['message' => 'No atiendes este examen.'], 403);
        }

        return response()->json([
            'data' => $incidencias->recientes($examenId, $aula, $limite),
        ]);
    }
}
