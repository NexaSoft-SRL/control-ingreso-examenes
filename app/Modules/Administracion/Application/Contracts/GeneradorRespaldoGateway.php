<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\RespaldoData;

/**
 * HU-25: Genera un respaldo físico en disco y registra su estado.
 */
interface GeneradorRespaldoGateway
{
    /**
     * Genera un respaldo de la base de datos y lo almacena en disco seguro.
     *
     * @param  string  $tipo  'puntual' o 'programado'
     * @param  int|null  $usuarioId  ID del usuario que solicita o null si es programado
     */
    public function generar(string $tipo, ?int $usuarioId = null): RespaldoData;
}

