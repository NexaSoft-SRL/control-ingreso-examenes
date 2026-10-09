<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Queries;

use App\Modules\Administracion\Application\Contracts\RolesGateway;
use App\Modules\Administracion\Application\DTOs\RolData;

/**
 * La matriz de permisos: los roles y el catalogo de pantallas.
 */
final readonly class ListarRoles
{
    public function __construct(
        private RolesGateway $roles,
    ) {}

    /**
     * @return list<RolData>
     */
    public function execute(): array
    {
        return $this->roles->listar();
    }

    /**
     * @return array<string, string> Clave => pantalla, en el orden de la matriz.
     */
    public function catalogo(): array
    {
        return $this->roles->catalogo();
    }
}
