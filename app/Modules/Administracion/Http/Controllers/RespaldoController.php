<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\DTOs\RespaldoData;
use App\Modules\Administracion\Application\Queries\ListarRespaldos;
use App\Modules\Administracion\Http\Requests\ListarRespaldosRequest;
use Illuminate\Http\JsonResponse;

/**
 * HU-25: Consulta del historial de respaldos, descarga de archivos .sql,
 * generacion a demanda y restauracion de la base de datos.
 */
final class RespaldoController
{
    public function index(ListarRespaldosRequest $request, ListarRespaldos $listar): JsonResponse
    {
        $pagina = $listar->execute($request->toData());

        return response()->json([
            'data' => array_map(
                fn (RespaldoData $respaldo): array => $this->serializar($respaldo),
                $pagina->filas,
            ),
            'meta' => [
                'total' => $pagina->total,
                'pagina' => $pagina->pagina,
                'por_pagina' => $pagina->porPagina,
            ],
            'conteos' => $pagina->conteos,
            'ultimo' => $pagina->ultimo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(RespaldoData $respaldo): array
    {
        return [
            'id' => $respaldo->id,
            'archivo' => $respaldo->archivo,
            'tipo' => ucfirst($respaldo->tipo),
            'tamano_bytes' => $respaldo->tamanoBytes,
            'estado' => ucfirst($respaldo->estado),
            'error' => $respaldo->error,
            'version_esquema' => $respaldo->versionEsquema,
            'usuario_id' => $respaldo->usuarioId,
            'fecha' => $respaldo->fecha,
            'created_at' => $respaldo->createdAt,
        ];
    }
}
