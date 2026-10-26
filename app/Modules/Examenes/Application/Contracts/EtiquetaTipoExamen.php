<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

/**
 * El nombre legible del tipo de examen («Primer parcial») para los modulos
 * que solo guardan su codigo (`PRIMER_PARCIAL`).
 */
interface EtiquetaTipoExamen
{
    public function de(string $tipo): string;
}
