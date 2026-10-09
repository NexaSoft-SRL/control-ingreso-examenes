<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Queries;

use App\Modules\Administracion\Application\Contracts\ConsultaUsuariosGateway;
use App\Modules\Administracion\Application\DTOs\FiltroUsuariosData;
use App\Modules\Administracion\Application\DTOs\PaginaUsuariosData;

final readonly class ListarUsuarios
{
    public function __construct(
        private ConsultaUsuariosGateway $usuarios,
    ) {}

    public function execute(FiltroUsuariosData $filtro): PaginaUsuariosData
    {
        return $this->usuarios->listar($filtro);
    }
}
