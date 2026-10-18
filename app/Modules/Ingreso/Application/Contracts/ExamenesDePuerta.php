<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface ExamenesDePuerta
{
    /**
     * Identificadores de exámenes programados para un aula y fecha.
     *
     * Esta consulta no concede permisos.
     *
     * @return list<int>
     */
    public function examenesDelAula(
        int $aulaId,
        string $fecha,
    ): array;
}
