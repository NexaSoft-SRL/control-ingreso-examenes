<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Una pagina de un listado, con el total de filas que cumplen el filtro y
 * los conteos que la pantalla muestra junto a sus filtros.
 */
final readonly class PaginaData
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, int>  $conteos
     */
    public function __construct(
        public array $filas,
        public int $total,
        public int $pagina,
        public int $porPagina,
        public array $conteos = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $meta = [
            'total' => $this->total,
            'pagina' => $this->pagina,
            'por_pagina' => $this->porPagina,
        ];

        if ($this->conteos !== []) {
            $meta['conteos'] = $this->conteos;
        }

        return $meta;
    }
}
