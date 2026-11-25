<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Una persona del equipo del examen: quien esta a cargo o de apoyo en alguna
 * de sus aulas. `puedeQuitar` vale para la cuenta que consulta: el docente que
 * registro el examen quita a cualquiera salvo a quien esta a cargo de un aula;
 * un colaborador, solo a quien el mismo sumo.
 */
final readonly class EquipoDeExamenData
{
    public function __construct(
        public int $id,
        public string $nombre,
        public bool $esDocente,
        public bool $esAuxiliar,
        public bool $aCargo,
        public ?int $agregadoPor,
        public ?string $agregadoPorNombre,
        public bool $puedeQuitar,
    ) {}
}
