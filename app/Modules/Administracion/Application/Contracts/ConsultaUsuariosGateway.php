<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\FiltroUsuariosData;
use App\Modules\Administracion\Application\DTOs\PaginaUsuariosData;
use App\Modules\Administracion\Application\DTOs\PermisoDePantallaData;
use App\Modules\Administracion\Application\DTOs\UsuarioConRolData;

/**
 * Lecturas de la pantalla de usuarios y roles.
 */
interface ConsultaUsuariosGateway
{
    /**
     * Una pagina de cuentas, las mas recientes primero.
     */
    public function listar(FiltroUsuariosData $filtro): PaginaUsuariosData;

    public function buscar(int $usuarioId): ?UsuarioConRolData;

    /**
     * El catalogo de permisos en el orden de la matriz, cada uno con los
     * roles de sistema que lo tienen vigente hoy.
     *
     * @return list<PermisoDePantallaData>
     */
    public function permisosPorPantalla(): array;
}
