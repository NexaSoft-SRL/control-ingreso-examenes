<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\CodigoAulaData;

interface CodigoAulaGateway
{
    /**
     * Nulo si el aula no es de ese examen.
     */
    public function consultar(int $examenId, int $aulaId): ?CodigoAulaData;

    /**
     * Genera el codigo de seis digitos del aula. Con `$regenerar` reemplaza
     * el que hubiera: el anterior deja de servir y los accesos abiertos con
     * el quedan cerrados. Sin `$regenerar` conserva el que ya exista.
     */
    public function generar(int $examenId, int $aulaId, int $usuarioId, bool $regenerar): ?CodigoAulaData;
}
