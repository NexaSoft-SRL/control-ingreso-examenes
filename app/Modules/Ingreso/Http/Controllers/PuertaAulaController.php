<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Controllers;

use App\Modules\Ingreso\Application\Contracts\DetallePuertaGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\Queries\ExamenesOperablesEnAula;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use App\Modules\Ingreso\Http\Middleware\ExigirPuesto;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La puerta de un aula (BUG-07, BUG-08): el aula con su edificio y piso y
 * los examenes de hoy que la persona atiende en ella, con su hora,
 * ingresados / asignados, si la puerta esta abierta y sus normas.
 */
final readonly class PuertaAulaController
{
    public function __invoke(
        Request $request,
        int $aula,
        ExamenesOperablesEnAula $operables,
        DetallePuertaGateway $detalle,
        VentanaPuntoControl $ventana,
    ): JsonResponse {
        $operador = ExigirPuesto::operador($request);

        $ahora = new DateTimeImmutable(
            'now',
            new DateTimeZone('America/La_Paz'),
        );
        $fecha = $ahora->format('Y-m-d');

        try {
            $autorizados = $operables->ejecutar($operador, $aula, $fecha);
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
