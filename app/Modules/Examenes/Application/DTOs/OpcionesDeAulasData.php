<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Las aulas sugeridas por los horarios de los grupos y, por aula, los
 * examenes con los que se compartiria. Es un aviso: no impide elegirlas.
 */
final readonly class OpcionesDeAulasData
{
    /**
     * @param  list<int>  $sugeridas
     * @param  array<int, list<string>>  $compartidas
     */
    public function __construct(
        public array $sugeridas,
        public array $compartidas,
    ) {}
}
