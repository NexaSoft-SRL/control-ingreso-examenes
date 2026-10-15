<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface IngresosRecientesGateway
{
    /**
     * @return list<array{
     *     ingreso_id: int,
     *     estudiante_id: int,
     *     codigo: string,
     *     nombres: string,
     *     apellidos: string,
     *     aula_id: int|null,
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
