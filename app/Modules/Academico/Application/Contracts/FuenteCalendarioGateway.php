<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Application\DTOs\CalendarioData;

interface FuenteCalendarioGateway
{
    /**
     * Los calendarios academicos registrados en `fuentes.json`, en el orden
     * del archivo. Vacio si el archivo no esta.
     *
     * @return list<CalendarioData>
     */
    public function calendarios(): array;
}
