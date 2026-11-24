<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Application\Queries\ConsultarCalendarioDeAulas;
use App\Modules\Examenes\Http\Requests\CalendarioDeAulasRequest;
use Illuminate\Http\JsonResponse;

final class CalendarioDeAulasController
{
    use RespuestasDeExamen;

    public function index(CalendarioDeAulasRequest $request, ConsultarCalendarioDeAulas $consultar): JsonResponse
    {
        $desde = $request->texto('desde');
        $hasta = $request->texto('hasta');

        return response()->json([
            'desde' => $desde,
            'hasta' => $hasta,
            // Un objeto por id de aula, tambien cuando no hay ninguna.
            'aulas' => (object) $consultar->execute($request->aulas(), $request->edificio(), $desde, $hasta, $this->usuarioId()),
        ]);
    }
}
