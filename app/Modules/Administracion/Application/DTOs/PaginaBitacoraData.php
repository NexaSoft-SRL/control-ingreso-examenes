<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class PaginaBitacoraData
{
    /**
     * @param  list<BitacoraOperacionData>  $filas
     * @param  list<string>  $operaciones  Claves de operacion que existen en la bitacora.
     */
    public function __construct(
        public array $filas,
        public int $total,
        public int $pagina,
        public int $porPagina,
        public array $operaciones,
        // Dia local del asiento mas antiguo, o null si esta vacia.
        public ?string $fechaMinima,
    ) {}
}
