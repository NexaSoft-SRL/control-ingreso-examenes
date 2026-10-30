<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\RespaldoData;

/**
 * HU-25: Restaura un respaldo en base de datos y obtiene archivos para descarga.
 */
interface RestauradorRespaldoGateway
{
    /**
     * Restaura la base de datos aplicando el volcado SQL del respaldo.
     */
    public function restaurar(int $respaldoId): RespaldoData;

    /**
     * Obtiene el nombre y contenido del archivo de respaldo para su descarga.
     *
     * @return array{nombre: string, contenido: string}
     */
    public function obtenerArchivo(int $respaldoId): array;
}
