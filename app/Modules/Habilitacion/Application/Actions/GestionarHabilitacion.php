<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Actions;

use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\EstudianteHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\ExamenHabilitacionData;

final readonly class GestionarHabilitacion
{
    public function __construct(
        private HabilitacionGateway $gateway,
    ) {}

    public function existeExamen(int $examenId): bool
    {
        return $this->gateway->existeExamen($examenId);
    }

    /** @return list<ExamenHabilitacionData> */
    public function listarExamenes(): array
    {
        return $this->gateway->listarExamenes();
    }

    /** @return list<EstudianteHabilitacionData> */
    public function listarEstudiantes(int $examenId): array
    {
        return $this->gateway->listarEstudiantes($examenId);
    }

    /** @param list<int> $estudianteIds */
    public function registrarCondiciones(
        int $examenId,
        array $estudianteIds,
        string $condicion,
        ?string $motivo,
        int $usuarioId,
    ): void {
        $this->gateway->registrarCondiciones(
            $examenId,
            $estudianteIds,
            $condicion,
            $motivo,
            $usuarioId,
        );
    }
}
