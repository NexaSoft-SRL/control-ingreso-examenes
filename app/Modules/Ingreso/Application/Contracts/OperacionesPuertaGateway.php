<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface OperacionesPuertaGateway
{
    public function auditarIntentoNoResuelto(
        int $aulaId,
        int $usuarioId,
        string $tipo,
        string $identificador,
        bool $ambiguo,
    ): void;

    public function fechaIngreso(
        int $examenId,
        ?int $estudianteId,
    ): ?string;
}
