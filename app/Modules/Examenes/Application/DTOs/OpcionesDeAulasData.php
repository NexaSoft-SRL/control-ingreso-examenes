<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Las aulas sugeridas por los horarios de los grupos y, por aula, los
 * examenes con los que se compartiria y las clases del horario a esa hora.
 * Es un aviso: no impide elegirlas.
 */
final readonly class OpcionesDeAulasData
{
    /**
     * @param  list<int>  $sugeridas
     * @param  array<int, list<string>>  $compartidas
     * @param  array<int, list<array{asignatura: string, grupo: string, docente: ?string, inicio: string, fin: string, propio: bool, auxiliatura: bool}>>  $clases
     */
    public function __construct(
        public array $sugeridas,
        public array $compartidas,
        public array $clases = [],
    ) {}
}
