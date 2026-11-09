<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Lo que ayuda a elegir las aulas, sin impedir ninguna: las sugeridas por los
 * horarios de los grupos, las clases del horario que caen a la hora
 * del examen y los otros examenes que se solapan. `compartidas` es el mismo
 * aviso de `examenes` en texto corto.
 */
final readonly class OpcionesDeAulasData
{
    /**
     * @param  list<int>  $sugeridas
     * @param  array<int, list<array{asignatura: string, grupo: string, docente: ?string, inicio: string, fin: string, propio: bool, auxiliatura: bool}>>  $clases
     * @param  array<int, list<array{examen: string, docente: ?string, inicio: string, fin: string, mismo_grupo: bool}>>  $examenes
     * @param  array<int, list<string>>  $compartidas
     */
    public function __construct(
        public array $sugeridas,
        public array $clases,
        public array $examenes,
        public array $compartidas,
    ) {}
}
