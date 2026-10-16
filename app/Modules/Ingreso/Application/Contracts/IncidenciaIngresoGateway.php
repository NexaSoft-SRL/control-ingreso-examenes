<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;

interface IncidenciaIngresoGateway
{
    public function registrar(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        VeredictoIngreso $veredicto,
        string $detalle,
    ): void;
}
