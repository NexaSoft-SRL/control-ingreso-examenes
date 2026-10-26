<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Contracts\DetallePuertaGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\Queries\ExamenesOperablesEnAula;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * La puerta de un aula (BUG-07, BUG-08): el aula con su edificio y piso y
 * los examenes de hoy que la persona atiende en ella, con su hora,
 * ingresados / asignados, si la puerta esta abierta y sus normas.
 */
final readonly class PuertaAulaController
{
    public function __invoke(
        int $aula,
        ExamenesOperablesEnAula $operables,
        DetallePuertaGateway $detalle,
        VentanaPuntoControl $ventana,
    ): JsonResponse {
        $usuarioId = Auth::id();

        if (! is_int($usuarioId)) {
            return response()->json([
                'message' => 'No hay una sesión activa.',
            ], 401);
        }

        $ahora = new DateTimeImmutable(
            'now',
            new DateTimeZone('America/La_Paz'),
        );
        $fecha = $ahora->format('Y-m-d');

        try {
            $autorizados = $operables->ejecutar($usuarioId, $aula, $fecha);
        } catch (AccesoPuntoDenegado $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 403);
        }

        $examenes = array_map(
            static fn (array $examen): array => [
                ...$examen,
                'abierto' => $ventana->estaAbierta($examen['id']),
            ],
            $detalle->examenesDelAula($aula, $autorizados),
        );

        return response()->json([
            'data' => [
                'aula' => $detalle->aula($aula),
                'fecha' => $fecha,
                'hora_servidor' => $ahora->format('H:i'),
                'abierta' => in_array(true, array_column($examenes, 'abierto'), true),
                'examenes' => $examenes,
            ],
        ]);
    }
}
