<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\FiltroUsuariosData;
use App\Modules\Administracion\Application\DTOs\PaginaUsuariosData;
use App\Modules\Administracion\Application\DTOs\UsuarioConRolData;

/**
 * Lecturas de la pantalla de usuarios.
 */
interface ConsultaUsuariosGateway
{
    /**
     * Una pagina de cuentas, las mas recientes primero.
     */
    public function listar(FiltroUsuariosData $filtro): PaginaUsuariosData;

    public function buscar(int $usuarioId): ?UsuarioConRolData;
}
