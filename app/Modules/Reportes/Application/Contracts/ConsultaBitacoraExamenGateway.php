<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

/**
 * @phpstan-type EventoBitacoraExamen array{
 *     id: string,
 *     ocurrida_en: string,
 *     categoria: string,
 *     suceso: string,
 *     estudiante: string,
 *     codigo: string|null,
 *     aula_id: int|null,
 *     aula: string|null,
 *     detalle: string,
 *     actor: string
 * }
 */
interface ConsultaBitacoraExamenGateway
{
    /**
     * @return array{id: int, tipo: string, asignatura: string, fecha: string, hora: string}|null
     */
    public function examen(int $examenId): ?array;

    public function existe(int $examenId): bool;

    /**
     * @return list<array{id: int, tipo: string, asignatura: string, fecha: string}>
     */
    public function examenesVisibles(int $usuarioId, bool $universidad): array;

    /**
     * @return list<array{id: int, nombre: string}>
     */
    public function aulas(int $examenId): array;

    /**
     * @return list<array{valor: string, etiqueta: string, total: int}>
     */
    public function categorias(int $examenId): array;

    /** @return array{data: list<EventoBitacoraExamen>, meta: array{total: int, total_examen: int, pagina: int, por_pagina: int, paginas: int}} */
    public function listar(
        int $examenId,
        ?string $categoria,
        ?string $buscar,
        ?int $aulaId,
        int $pagina,
    ): array;

    /**
     * @return list<EventoBitacoraExamen>
     */
    public function todos(int $examenId): array;
}
