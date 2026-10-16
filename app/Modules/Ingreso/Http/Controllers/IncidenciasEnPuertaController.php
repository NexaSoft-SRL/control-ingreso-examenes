<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use App\Modules\Ingreso\Application\Contracts\IncidenciasIngresoGateway;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final readonly class IncidenciasEnPuertaController
{
    public function __invoke(
        Request $request,
        int $aula,
        ExamenesDePuerta $examenesDePuerta,
        ExamenGateway $examenes,
        AlcancePuntoControl $alcance,
        IncidenciasIngresoGateway $incidencias,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        $request->validate([
            'limite' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        if ($aula <= 0) {
            return response()->json([
                'message' => 'Aula inválida.',
            ], 422);
        }

        $limite = $request->integer('limite', 20);

        $fecha = (new DateTimeImmutable(
            'now',
            new DateTimeZone('America/La_Paz'),
        ))->format('Y-m-d');

        $ids = $examenesDePuerta->examenesDelAula(
            $aula,
            $fecha,
        );

        $autorizados = [];

        foreach (array_unique($ids) as $examenId) {
            $examen = $examenes->actual($examenId);

            if (
                $examen !== null
                && in_array($aula, $examen->aulas, true)
                && $alcance->puedeOperar(
                    $usuarioId,
                    $examenId,
                    $aula,
                )
            ) {
                $autorizados[] = $examenId;
            }
        }

        if ($autorizados === []) {
            return response()->json([
                'message' => 'No atiendes esta aula.',
            ], 403);
        }

        $filas = [];

        foreach ($autorizados as $examenId) {
            foreach (
                $incidencias->recientes($examenId, $aula, $limite) as $fila
            ) {
                $filas[] = [
                    ...$fila,
                    'examen_id' => $examenId,
                ];
            }
        }

        usort(
            $filas,
            static fn (array $a, array $b): int => strcmp(
                $b['registrado_en'],
                $a['registrado_en'],
            ) ?: ($b['incidencia_id'] <=> $a['incidencia_id']),
        );

        $filas = array_slice($filas, 0, $limite);

        return response()->json([
            'data' => $filas,
            'meta' => ['cantidad' => count($filas)],
        ]);
    }
}
