<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\GenerarRespaldo;
use App\Modules\Administracion\Application\Actions\GuardarAjustesDeRespaldo;
use App\Modules\Administracion\Application\Actions\RestaurarRespaldo;
use App\Modules\Administracion\Application\DTOs\AjustesDeRespaldoData;
use App\Modules\Administracion\Application\DTOs\RespaldoData;
use App\Modules\Administracion\Application\Queries\ConsultarAjustesDeRespaldo;
use App\Modules\Administracion\Application\Queries\DescargarRespaldo;
use App\Modules\Administracion\Application\Queries\ListarRespaldos;
use App\Modules\Administracion\Domain\Exceptions\RespaldoBloqueadoException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoFallidoException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoIncompatibleException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoNoEncontradoException;
use App\Modules\Administracion\Domain\ValueObjects\InstanteLocal;
use App\Modules\Administracion\Http\Requests\GuardarAjustesDeRespaldoRequest;
use App\Modules\Administracion\Http\Requests\ListarRespaldosRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Respaldo y restauracion. El respaldo tambien se genera solo cada noche:
 * ver la tarea programada en routes/console.php.
 */
final class RespaldoController
{
    public function index(
        ListarRespaldosRequest $request,
        ListarRespaldos $listar,
        ConsultarAjustesDeRespaldo $ajustes,
    ): JsonResponse {
        $pagina = $listar->execute(
            $request->filtro(),
            $request->pagina(),
            $request->porPagina(),
        );

        return response()->json([
            'data' => array_map(
                fn (RespaldoData $respaldo): array => $this->serializar($respaldo),
                $pagina->filas,
            ),
            'meta' => [
                'total' => $pagina->total,
                'pagina' => $pagina->pagina,
                'por_pagina' => $pagina->porPagina,
                'conteos' => $pagina->conteos,
                'programacion' => $ajustes->execute()->programacion(),
                'ultimo' => $pagina->ultimo,
            ],
        ]);
    }

    public function ajustes(ConsultarAjustesDeRespaldo $consultar): JsonResponse
    {
        return response()->json(['data' => $this->serializarAjustes($consultar->execute())]);
    }

    public function guardarAjustes(
        GuardarAjustesDeRespaldoRequest $request,
        GuardarAjustesDeRespaldo $guardar,
        ConsultarAjustesDeRespaldo $consultar,
    ): JsonResponse {
        $guardar->execute((int) Auth::id(), $request->ajustes());

        return response()->json([
            'message' => 'Ajustes guardados.',
            'data' => $this->serializarAjustes($consultar->execute()),
        ]);
    }

    /**
     * @return array{habilitado: bool, hora: string, reintento_minutos: int, programacion: string}
     */
    private function serializarAjustes(AjustesDeRespaldoData $ajustes): array
    {
        return [
            'habilitado' => $ajustes->habilitado,
            'hora' => $ajustes->hora,
            'reintento_minutos' => $ajustes->reintentoMinutos,
            'programacion' => $ajustes->programacion(),
        ];
    }

    public function store(GenerarRespaldo $generar): JsonResponse
    {
        $respaldo = $generar->execute($this->usuarioId());

        if (! $respaldo->esCorrecto()) {
            return response()->json([
                'message' => 'No se pudo generar el respaldo.',
                'data' => $this->serializar($respaldo),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'data' => $this->serializar($respaldo),
            'message' => 'Respaldo generado.',
        ], Response::HTTP_CREATED);
    }

    public function descargar(int $respaldo, DescargarRespaldo $descargar): Response|JsonResponse
    {
        try {
            $archivo = $descargar->execute($respaldo);
        } catch (RespaldoNoEncontradoException $excepcion) {
            return $this->error($excepcion->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (RespaldoFallidoException $excepcion) {
            return $this->error($excepcion->getMessage(), Response::HTTP_CONFLICT, 'RESPALDO_FALLIDO');
        }

        return response($archivo->contenido, Response::HTTP_OK, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="'.$archivo->nombre.'"',
        ]);
    }

    public function restaurar(int $respaldo, RestaurarRespaldo $restaurar): JsonResponse
    {
        try {
            $restaurado = $restaurar->execute($respaldo, $this->usuarioId());
        } catch (RespaldoNoEncontradoException $excepcion) {
            return $this->error($excepcion->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (RespaldoFallidoException $excepcion) {
            return $this->error($excepcion->getMessage(), Response::HTTP_CONFLICT, 'RESPALDO_FALLIDO');
        } catch (RespaldoIncompatibleException $excepcion) {
            return $this->error($excepcion->getMessage(), Response::HTTP_CONFLICT, 'RESPALDO_INCOMPATIBLE');
        } catch (RespaldoBloqueadoException $excepcion) {
            return $this->error($excepcion->getMessage(), Response::HTTP_CONFLICT, 'EXAMEN_EN_CURSO');
        }

        return response()->json([
            'message' => sprintf(
                'Restaurado al %s.',
                InstanteLocal::de($restaurado->generadoEn)->corto(),
            ),
            'tablas' => $restaurado->tablas,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(RespaldoData $respaldo): array
    {
        return [
            'id' => $respaldo->id,
            'fecha' => $respaldo->generadoEn,
            'tipo' => $respaldo->tipo === RespaldoData::TIPO_PROGRAMADO ? 'Programado' : 'Puntual',
            'tamano_bytes' => $respaldo->tamanoBytes,
            'estado' => $respaldo->esCorrecto() ? 'correcto' : 'fallido',
            'restaurado' => $respaldo->restaurado,
            'archivo' => $respaldo->archivo,
        ];
    }

    private function error(string $mensaje, int $estado, ?string $codigo = null): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $mensaje,
            'codigo' => $codigo,
        ]), $estado);
    }

    private function usuarioId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }
}
