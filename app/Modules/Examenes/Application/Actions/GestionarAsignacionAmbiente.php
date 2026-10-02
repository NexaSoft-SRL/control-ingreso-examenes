<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AsignacionAmbienteGateway;

/**
 * Distribución de los estudiantes habilitados entre los ambientes del
 * examen (HU-14).
 */
final readonly class GestionarAsignacionAmbiente
{
    public function __construct(
        private AsignacionAmbienteGateway $gateway,
    ) {}

    public function existeExamen(int $examenId): bool
    {
        return $this->gateway->existeExamen($examenId);
    }

    /** @return array{candidatos: list<array<string, mixed>>, ambientes: list<array<string, mixed>>} */
    public function listar(int $examenId): array
    {
        return [
            'candidatos' => $this->gateway->candidatos($examenId),
            'ambientes' => $this->gateway->ambientes($examenId),
        ];
    }

    /**
     * Devuelve el motivo del rechazo, o null si la asignación se registró.
     *
     * @param  list<int>  $estudianteIds
     */
    public function asignar(int $examenId, int $ambienteId, array $estudianteIds, ?int $usuarioId): ?string
    {
        $ids = array_values(array_unique($estudianteIds));

        if (array_diff($ids, $this->gateway->idsHabilitados($examenId)) !== []) {
            return 'Solo pueden asignarse estudiantes habilitados y activos.';
        }

        if ($this->gateway->idsYaAsignados($examenId, $ids) !== []) {
            return 'Uno o más estudiantes ya están asignados a este examen.';
        }

        $capacidad = $this->gateway->capacidadAmbiente($ambienteId) ?? 0;

        if ($this->gateway->ocupados($examenId, $ambienteId) + count($ids) > $capacidad) {
            return 'El ambiente no tiene cupo suficiente para la cantidad solicitada.';
        }

        $this->gateway->asignar($examenId, $ambienteId, $ids, $usuarioId);

        return null;
    }

    /** @return array<string, mixed> */
    public function detalleAmbiente(int $examenId, int $ambienteId): array
    {
        return $this->gateway->detalleAmbiente($examenId, $ambienteId);
    }

    public function quitar(int $examenId, int $ambienteId, int $estudianteId): bool
    {
        return $this->gateway->quitar($examenId, $ambienteId, $estudianteId) > 0;
    }
}
