<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class PaginaRespaldosData
{
    /**
     * @param  list<RespaldoData>  $filas
     * @param  array<string, int>  $conteos
     */
    public function __construct(
        public array $filas,
        public int $total,
        public int $pagina,
        public int $porPagina,
        public array $conteos,
        public ?string $ultimo,
    ) {}
}
