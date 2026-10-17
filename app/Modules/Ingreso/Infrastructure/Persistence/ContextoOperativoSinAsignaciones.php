<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\ContextoOperativoPuntoControl;

class ContextoOperativoSinAsignaciones implements ContextoOperativoPuntoControl
{
    public function examenesAutorizados(int $usuarioId): array
    {
        return [];
    }
}
