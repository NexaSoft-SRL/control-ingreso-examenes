<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\DescargarRespaldo;
use App\Modules\Administracion\Application\Actions\GenerarRespaldo;
use App\Modules\Administracion\Application\Actions\RestaurarRespaldo;
use App\Modules\Administracion\Application\DTOs\RespaldoData;
use App\Modules\Administracion\Application\Queries\ListarRespaldos;
use App\Modules\Administracion\Domain\Exceptions\ArchivoRespaldoNoEncontradoException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoFallidoException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoIncompatibleException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoNoEncontradoException;
use App\Modules\Administracion\Http\Requests\ListarRespaldosRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

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

    public function store(Request $request, GenerarRespaldo $generar): JsonResponse
    {
        $identificador = $request->user()?->getAuthIdentifier();
        $usuarioId = is_numeric($identificador) ? (int) $identificador : null;

        try {
            $respaldo = $generar->execute('puntual', $usuarioId);

            return response()->json([
                'data' => $this->serializar($respaldo),
                'message' => 'Respaldo generado.',
            ], 201);
        } catch (Throwable $error) {
            return response()->json([
                'message' => 'No se pudo generar el respaldo: '.$error->getMessage(),
            ], 500);
        }
    }

    public function descargar(int $id, DescargarRespaldo $descargar): Response|JsonResponse
    {
        try {
            $archivo = $descargar->execute($id);

            return response($archivo['contenido'], 200, [
                'Content-Type' => 'application/sql',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $archivo['nombre']),
                'Cache-Control' => 'no-cache, private',
            ]);
        } catch (RespaldoNoEncontradoException|ArchivoRespaldoNoEncontradoException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 404);
        } catch (RespaldoFallidoException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 422);
        } catch (Throwable $error) {
            return response()->json([
                'message' => 'No se pudo descargar el archivo: '.$error->getMessage(),
            ], 500);
        }
    }

    public function restaurar(Request $request, int $id, RestaurarRespaldo $restaurar): JsonResponse
    {
        $identificador = $request->user()?->getAuthIdentifier();
        $usuarioId = is_numeric($identificador) ? (int) $identificador : null;

        try {
            $respaldo = $restaurar->execute($id, $usuarioId);

            return response()->json([
                'message' => 'Restaurado con éxito',
                'data' => $this->serializar($respaldo),
            ], 200);
        } catch (RespaldoNoEncontradoException|ArchivoRespaldoNoEncontradoException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 404);
        } catch (RespaldoIncompatibleException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 422);
        } catch (RespaldoFallidoException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 422);
        } catch (Throwable $error) {
            return response()->json([
                'message' => 'No se pudo restaurar el respaldo: '.$error->getMessage(),
            ], 500);
        }
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
