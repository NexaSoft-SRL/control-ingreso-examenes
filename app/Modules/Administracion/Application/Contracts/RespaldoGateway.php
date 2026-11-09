<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\PaginaRespaldosData;
use App\Modules\Administracion\Application\DTOs\RespaldoData;

interface RespaldoGateway
{
    /**
     * Genera un respaldo de los datos, lo guarda y deja su fila en el
     * historial. No lanza si la generacion falla: la fila queda FALLIDO con
     * su error y eso es lo que devuelve.
     *
     * @param  string  $tipo  `RespaldoData::TIPO_PROGRAMADO` o `TIPO_PUNTUAL`.
     */
    public function generar(string $tipo, ?int $usuarioId): RespaldoData;

    /**
     * Una pagina del historial, del mas reciente al mas antiguo.
     *
     * @param  string|null  $filtro  `programados`, `puntuales`, `fallidos` o null (todos).
     */
    public function listar(?string $filtro, int $pagina, int $porPagina): PaginaRespaldosData;

    public function buscar(int $respaldoId): ?RespaldoData;

    /**
     * Contenido del guion SQL, para descargarlo o importarlo a mano desde
     * el panel del alojamiento. Null si el archivo ya no esta en disco.
     */
    public function contenido(int $respaldoId): ?string;

    /**
     * Si el guion es del esquema actual. Uno anterior al replanteo nombra
     * tablas que ya no existen.
     */
    public function esCompatible(string $contenido): bool;

    /**
     * Repone los datos del respaldo, conserva el historial de respaldos,
     * marca la fila como restaurada y lo asienta en la bitacora, todo en
     * una transaccion. Devuelve cuantas tablas se repusieron.
     */
    public function restaurar(int $respaldoId, ?int $usuarioId): int;
}
