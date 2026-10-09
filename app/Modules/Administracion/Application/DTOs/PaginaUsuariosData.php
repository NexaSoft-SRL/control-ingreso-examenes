<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class PaginaUsuariosData
{
    /**
     * @param  list<UsuarioConRolData>  $filas
     * @param  array<string, int>  $conteos  Cuentas de cada rol existente (por nombre) y `bloqueadas`, sobre todas las cuentas.
     */
    public function __construct(
        public array $filas,
        public int $total,
        public int $pagina,
        public int $porPagina,
        public array $conteos,
        // Todas las cuentas, sin filtros.
        public int $cuentas,
    ) {}
}
