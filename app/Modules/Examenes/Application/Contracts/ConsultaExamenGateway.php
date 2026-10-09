<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\ExamenDetalleData;
use App\Modules\Examenes\Application\DTOs\ExamenResumenData;
use App\Modules\Examenes\Application\DTOs\GrupoDeExamenData;

/**
 * Lecturas del examen y de lo que el asistente de registro ofrece.
 */
interface ConsultaExamenGateway
{
    /**
     * Los examenes del docente (los que registro y los que incluyen un
     * grupo suyo), por fecha y hora. `$periodoIds` nulo = sin filtrar.
     *
     * @param  list<int>|null  $periodoIds
     * @return list<ExamenResumenData>
     */
    public function listar(int $usuarioId, ?array $periodoIds): array;

    /**
     * El examen completo. `propio` se calcula respecto de `$usuarioId`;
     * no comprueba el alcance.
     */
    public function detalle(int $examenId, int $usuarioId): ?ExamenDetalleData;

    /**
     * Los grupos de la asignatura en esos periodos, por codigo.
     *
     * @param  list<int>  $periodoIds
     * @return list<GrupoDeExamenData>
     */
    public function gruposDeAsignatura(int $asignaturaId, array $periodoIds, int $usuarioId): array;

    /**
     * Los codigos de los grupos que ya estan en otro examen de ese tipo.
     *
     * @param  list<int>  $grupoIds
     * @return list<string>
     */
    public function gruposConExamenDeTipo(array $grupoIds, string $tipo, ?int $exceptoExamenId): array;

    /**
     * Las aulas ubicadas (con edificio) de los horarios de esos grupos.
     *
     * @param  list<int>  $grupoIds
     * @return list<int>
     */
    public function aulasSugeridas(array $grupoIds): array;

    /**
     * Por aula, los examenes que se solapan con ese horario.
     *
     * @return array<int, list<string>>
     */
    public function aulasCompartidas(
        string $fecha,
        string $horaInicio,
        int $duracionMinutos,
        ?int $exceptoExamenId,
    ): array;

    /**
     * El periodo pedido en `?periodo=`, por id o por codigo (`2/2026`).
     *
     * @return array{id: int, codigo: string}|null
     */
    public function periodo(string $idOCodigo): ?array;

    public function hoy(): string;

    /**
     * El instante actual, ISO 8601 con la zona de la universidad.
     */
    public function horaServidor(): string;
}
