<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface SeguimientoAulaGateway
{
    /**
     * @return list<array<string, mixed>>
     */
    public function aulasDelUsuario(int $usuarioId): array;

    /**
     * Las aulas de los exámenes de hoy que la cuenta puede seguir («Mi aula»).
     *
     * @return list<array<string, mixed>>
     */
    public function aulasDeHoy(int $usuarioId): array;

    public function aulaPerteneceAExamen(int $examenId, int $aulaId): bool;

    public function fechaDeExamen(int $examenId): ?string;

    public function tieneAsignacion(int $usuarioId, int $examenId, int $aulaId): bool;

    /**
     * Docente del examen o personal asignado al aula, sin mirar la fecha.
     */
    public function tieneAlcance(int $usuarioId, int $examenId, int $aulaId): bool;

    public function puedeVerAula(int $usuarioId, int $examenId, int $aulaId): bool;

    /**
     * @return array{
     *     examen: array<string, mixed>,
     *     aula: array<string, mixed>,
     *     cifras: array{ingresaron: int, faltan: int, asignados: int, incidencias: int, de_otra_aula: int, en_otra_aula: int},
     *     estudiantes: list<array<string, mixed>>,
     *     pagina: array{actual: int, ultima: int, por_pagina: int, total: int}
     * }
     */
    public function detalle(
        int $examenId,
        int $aulaId,
        string $estado,
        string $busqueda,
        int $pagina,
    ): array;
}
