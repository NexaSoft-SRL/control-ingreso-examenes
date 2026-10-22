<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface SeguimientoAulaGateway
{
    /**
     * @return list<array<string, mixed>>
     */
    public function aulasDelUsuario(int $usuarioId): array;

    public function aulaPerteneceAExamen(int $examenId, int $aulaId): bool;

    public function fechaDeExamen(int $examenId): ?string;

    public function tieneAsignacion(int $usuarioId, int $examenId, int $aulaId): bool;

    public function puedeVerAula(int $usuarioId, int $examenId, int $aulaId): bool;

    /**
     * @return array{
     *     examen: array<string, mixed>,
     *     aula: array<string, mixed>,
     *     cifras: array{ingresaron: int, faltan: int, asignados: int, incidencias: int},
     *     estudiantes: list<array<string, mixed>>,
     *     incidencias: list<array<string, mixed>>,
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

    /**
     * @return array{examen_id: int, aula_id: int}|null
     */
    public function ubicacionIncidencia(int $incidenciaId): ?array;

    public function marcarIncidenciaAtendida(
        int $incidenciaId,
        int $usuarioId,
    ): bool;
}
