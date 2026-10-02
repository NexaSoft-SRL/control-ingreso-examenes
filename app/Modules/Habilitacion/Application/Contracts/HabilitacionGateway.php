<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Contracts;

use App\Modules\Habilitacion\Application\DTOs\ConsultaHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\EstudianteHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\ExamenHabilitacionData;

interface HabilitacionGateway
{
    public function existeExamen(int $examenId): bool;

    /** @return list<ExamenHabilitacionData> */
    public function listarExamenes(): array;

    /** @return list<EstudianteHabilitacionData> */
    public function listarEstudiantes(int $examenId): array;

    /** @return list<ConsultaHabilitacionData> */
    public function consultarPorIdentificador(int $examenId, string $identificador): array;

    /** @param list<int> $estudianteIds */
    public function registrarCondiciones(
        int $examenId,
        array $estudianteIds,
        string $condicion,
        ?string $motivo,
        int $usuarioId,
    ): void;
}
