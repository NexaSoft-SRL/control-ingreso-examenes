<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface IncidenciaManualPuertaGateway
{
    /**
     * @return list<array<string, int|string|null>>
     */
    public function recientes(int $examenId, int $aulaId, int $limite): array;

    public function estudiantePorCodigoEnExamen(
        int $examenId,
        string $codigo,
    ): ?int;

    public function estudiantePerteneceAlExamen(
        int $examenId,
        int $estudianteId,
    ): bool;

    public function registrar(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        string $tipo,
        string $detalle,
    ): int;
}
