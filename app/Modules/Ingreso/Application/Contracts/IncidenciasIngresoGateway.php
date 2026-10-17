<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface IncidenciasIngresoGateway
{
    /**
     * @return list<array{
     *     incidencia_id: int,
     *     estudiante_id: int|null,
     *     codigo: string|null,
     *     nombres: string|null,
     *     apellidos: string|null,
     *     aula_id: int,
     *     tipo_rechazo: string,
     *     detalle: string,
     *     registrado_en: string,
     *     operador: string|null
     * }>
     */
    public function recientes(
        int $examenId,
        int $aulaId,
        int $limite,
    ): array;
}
