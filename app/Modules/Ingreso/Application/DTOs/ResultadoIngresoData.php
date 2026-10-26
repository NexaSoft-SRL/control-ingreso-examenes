<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;

/**
 * Lo que resolvio la puerta al registrar: el veredicto y si dejo asentada
 * una incidencia por el ingreso repetido (la puerta lo dice al pie).
 */
final readonly class ResultadoIngresoData
{
    public function __construct(
        public VeredictoIngreso $veredicto,
        public bool $incidenciaRegistrada = false,
    ) {}
}
