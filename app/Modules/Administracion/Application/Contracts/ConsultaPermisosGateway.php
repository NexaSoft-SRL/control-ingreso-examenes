<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

interface ConsultaPermisosGateway
{
    public function tienePermiso(int $usuarioId, string $permiso): bool;
}
