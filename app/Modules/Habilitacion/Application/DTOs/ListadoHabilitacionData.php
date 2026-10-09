<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * Una pagina de la lista de habilitacion con todo lo que la pantalla
 * muestra a su alrededor.
 */
final readonly class ListadoHabilitacionData
{
    /**
     * @param  list<InscritoData>  $filas
     * @param  list<AulaRepartoData>  $porAula
     * @param  array{todos: int, habilitado: int, no: int, pendiente: int}  $condiciones
     * @param  list<GrupoDeExamenData>  $grupos
     */
    public function __construct(
        public array $filas,
        public int $total,
        public int $pagina,
        public int $porPagina,
        public CifrasHabilitacionData $cifras,
        public array $porAula,
        public array $condiciones,
        public array $grupos,
    ) {}
}
