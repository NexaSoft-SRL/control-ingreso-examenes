<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

interface GeneradorContrasenaTemporal
{
    /**
     * Contrasena con el formato `Xx9-Xxx9-Xx9`: facil de dictar y de
     * copiar a mano, sin caracteres que se confundan.
     */
    public function generar(): string;
}
