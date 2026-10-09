<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * La pagina que pide un listado paginado del lado del servidor.
 */
final readonly class PaginaData
{
    public function __construct(
        public int $pagina,
        public int $porPagina,
    ) {}

    /**
     * @return array{total: int, pagina: int, por_pagina: int}
     */
    public function meta(int $total): array
    {
        return [
            'total' => $total,
            'pagina' => $this->pagina,
            'por_pagina' => $this->porPagina,
        ];
    }
}
