<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\AmbienteAsignadoData;
use App\Modules\Examenes\Application\DTOs\OcupacionExamenData;

interface AmbienteExamenGateway
{
    /**
     * @return list<AmbienteAsignadoData>
     */
    public function listarDeExamen(int $examenId): array;

    public function estaDisponible(int $examenId, int $ambienteId): bool;

    public function asignar(
        int $examenId,
        int $ambienteId,
        ?int $usuarioId,
    ): AmbienteAsignadoData;

    public function quitar(
        int $examenId,
        int $ambienteId,
        ?int $usuarioId,
    ): bool;

    public function ocupacion(int $examenId): OcupacionExamenData;
}
