<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface VentanaPuntoControl
{
    public function estaAbierta(int $examenId): bool;
}
