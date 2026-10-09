<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Application\DTOs\PeriodoData;

interface PeriodoGateway
{
    /**
     * Los periodos cuyas fechas contienen el dia de hoy, del mas reciente
     * al mas antiguo. Pueden ser varios a la vez.
     *
     * @return list<PeriodoData>
     */
    public function vigentes(): array;

    /**
     * El vigente con mas grupos; null si hoy no cae en ningun periodo.
     */
    public function principal(): ?PeriodoData;
}
