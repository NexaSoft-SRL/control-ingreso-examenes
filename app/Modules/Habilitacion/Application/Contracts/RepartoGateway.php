<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Contracts;

use App\Modules\Habilitacion\Application\DTOs\AulaRepartoData;
use App\Modules\Habilitacion\Application\DTOs\RepartoData;

interface RepartoGateway
{
    /**
     * Las aulas del examen, en el orden en que se le asignaron, con sus
     * estudiantes asignados.
     *
     * @return list<AulaRepartoData>
     */
    public function porAula(int $examenId): array;

    /**
     * Reparte solo a los habilitados sin aula, por apellidos y nombres,
     * cada uno al aula con menos asignados. No mueve a quien ya tiene aula.
     */
    public function repartir(int $examenId, int $usuarioId): RepartoData;
}
