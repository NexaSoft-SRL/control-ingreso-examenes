<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use App\Modules\Ingreso\Application\Contracts\IngresosRecientesGateway;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final readonly class IngresosRecientesEnPuertaController
{
    public function __invoke(
        int $aula,
        ExamenesDePuerta $examenesDePuerta,
        ExamenGateway $examenes,
        AlcancePuntoControl $alcance,
        IngresosRecientesGateway $ingresos,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        if ($aula <= 0) {
            return response()->json([
                'message' => 'Aula inválida.',
            ], 422);
        }

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
            foreach ($ingresos->recientes($examenId, $aula, 5) as $fila) {
                $filas[] = [
                    ...$fila,
                    'examen_id' => $examenId,
                ];
            }
        }

        usort(
            $filas,
            static fn (array $a, array $b): int => (strcmp(
                $b['registrado_en'],
                $a['registrado_en'],
            ) ?: ($b['ingreso_id'] <=> $a['ingreso_id'])),
        );

        $filas = array_slice($filas, 0, 5);

        return response()->json([
            'data' => $filas,
            'meta' => [
                'cantidad' => count($filas),
            ],
        ]);
    }
}
