<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

use App\Modules\Reportes\Application\DTOs\ArchivoReporte;
use App\Modules\Reportes\Application\DTOs\TablaReporte;

interface GeneradorTablaReporte
{
    public function generar(TablaReporte $tabla, string $formato): ArchivoReporte;
}
