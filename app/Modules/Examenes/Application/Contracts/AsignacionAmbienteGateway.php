<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

interface AsignacionAmbienteGateway
{
    public function existeExamen(int $examenId): bool;

    /**
     * Habilitados y activos que todavía no tienen ambiente en el examen.
     *
     * @return list<array<string, mixed>>
     */
    public function candidatos(int $examenId): array;

    /**
     * Cada ambiente con su ocupación y sus estudiantes en el examen.
     *
     * @return list<array<string, mixed>>
     */
    public function ambientes(int $examenId): array;

    /** @return array<string, mixed> */
    public function detalleAmbiente(int $examenId, int $ambienteId): array;

    public function capacidadAmbiente(int $ambienteId): ?int;

    public function ocupados(int $examenId, int $ambienteId): int;

    /** @return list<int> */
    public function idsHabilitados(int $examenId): array;

    /**
     * @param  list<int>  $estudianteIds
     * @return list<int>
     */
    public function idsYaAsignados(int $examenId, array $estudianteIds): array;

    /** @param list<int> $estudianteIds */
    public function asignar(int $examenId, int $ambienteId, array $estudianteIds, ?int $usuarioId): void;

    public function quitar(int $examenId, int $ambienteId, int $estudianteId): int;
}
