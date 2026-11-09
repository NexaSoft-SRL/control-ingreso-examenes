<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class PaginaRespaldosData
{
    /**
     * @param  list<RespaldoData>  $filas
     * @param  array{todos: int, programados: int, puntuales: int, fallidos: int}  $conteos
     */
    public function __construct(
        public array $filas,
        public int $total,
        public int $pagina,
        public int $porPagina,
        public array $conteos,
        // Instante del ultimo respaldo correcto, o null.
        public ?string $ultimo,
    ) {}
}
