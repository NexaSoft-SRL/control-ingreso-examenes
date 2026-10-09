<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

/**
 * Dibuja la plantilla de inscritos como hoja de calculo.
 */
interface GeneradorPlantilla
{
    /**
     * @param  list<string>  $encabezados
     * @return string el contenido binario del `.xlsx`
     */
    public function generar(array $encabezados): string;
}
