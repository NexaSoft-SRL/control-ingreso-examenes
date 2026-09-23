<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\ConsultarBitacora;
use App\Modules\Administracion\Application\DTOs\BitacoraOperacionData;
use App\Modules\Administracion\Http\Requests\ConsultarBitacoraRequest;
use Illuminate\Http\JsonResponse;

final class BitacoraController
{
    public function index(
        ConsultarBitacoraRequest $request,
        ConsultarBitacora $consultarBitacora,
    ): JsonResponse {
        $data = array_map(
            fn (BitacoraOperacionData $operacion): array => $this->serialize(
                $operacion
            ),
            $consultarBitacora->execute(
                $request->toData()
            ),
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(
        BitacoraOperacionData $operacion,
    ): array {
        $usuario = $operacion->usuarioId === null
            ? null
            : [
                'id' => $operacion->usuarioId,
                'name' => $operacion->usuarioNombre,
                'email' => $operacion->usuarioEmail,
            ];

        return [
            'id' => $operacion->id,
            'usuario' => $usuario,
            'operacion' => $operacion->operacion,
            'tabla_afectada' => $operacion->tablaAfectada,
            'registro_id' => $operacion->registroId,
            'descripcion' => $operacion->descripcion,
            'fecha_operacion' => $operacion->fechaOperacion,
        ];
    }
}
