<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Domain\Enums\TipoExcepcion;

interface OperacionesPuertaGateway
{
    public function fechaIngreso(
        int $examenId,
        ?int $estudianteId,
    ): ?string;

    public function examenDeIngreso(
        int $ingresoId,
        int $aulaId,
    ): ?int;

    /**
     * La expulsion queda como incidencia del ingreso; falso si ya tenia.
     */
    public function expulsar(
        int $ingresoId,
        int $aulaId,
        int $usuarioId,
        string $motivo,
    ): bool;

    public function estudiantePerteneceAlExamen(int $examenId, int $estudianteId): bool;

    /**
     * Hora («09:10») de la expulsion del ingreso, o nulo si no la tiene.
     */
    public function horaExpulsion(int $ingresoId): ?string;

    /**
     * Situacion excepcional: siempre queda como incidencia del aula (del
     * tipo que le corresponde en el catalogo) y en la bitacora; con
     * `$admitir`, ademas
     * registra el ingreso marcado como excepcion, con el motivo. Devuelve
     * nulo si el estudiante ya tenia ingreso en el examen.
     *
     * @return array{incidencia_id: int, ingreso: array{id: int, hora: string}|null}|null
     */
    public function registrarExcepcion(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        TipoExcepcion $tipo,
        string $motivo,
    ): ?array;
}
