<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\DTOs\BitacoraOperacionData;
use App\Modules\Administracion\Application\Queries\ConsultarBitacora;
use App\Modules\Administracion\Http\Requests\ConsultarBitacoraRequest;
use Illuminate\Http\JsonResponse;

/**
 * La bitacora solo se consulta: no hay ruta que la escriba ni la borre.
 */
final class BitacoraController
{
    public function index(
        ConsultarBitacoraRequest $request,
        ConsultarBitacora $consultar,
    ): JsonResponse {
        $pagina = $consultar->execute($request->toData());

        return response()->json([
            'data' => array_map(
                static fn (BitacoraOperacionData $asiento): array => [
                    'id' => $asiento->id,
                    'usuario' => $asiento->usuario ?? 'Sistema',
                    'rol' => $asiento->rol,
                    'operacion' => $asiento->operacion,
                    'operacion_texto' => ConsultarBitacora::texto($asiento->operacion),
                    'entidad' => $asiento->descripcion,
                    'fecha' => $asiento->fecha,
                ],
                $pagina->filas,
            ),
            'meta' => [
                'total' => $pagina->total,
                'pagina' => $pagina->pagina,
                'por_pagina' => $pagina->porPagina,
                'operaciones' => $consultar->opciones($pagina->operaciones),
                'fecha_minima' => $pagina->fechaMinima,
            ],
        ]);
    }
}
