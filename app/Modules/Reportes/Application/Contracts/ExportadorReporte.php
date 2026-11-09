<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

use App\Modules\Reportes\Application\DTOs\TablaReporte;

interface ExportadorReporte
{
    /**
     * Devuelve el contenido del archivo PDF (HU-34).
     */
    public function pdf(TablaReporte $tabla): string;

    /**
     * Devuelve el contenido de la hoja de calculo en formato xlsx (HU-34).
     */
    public function hojaDeCalculo(TablaReporte $tabla): string;
}
