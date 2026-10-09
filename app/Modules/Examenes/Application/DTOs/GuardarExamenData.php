<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Todo el asistente de registro, que se guarda de una vez. El periodo lo
 * resuelve la accion a partir de los grupos.
 */
final readonly class GuardarExamenData
{
    /**
     * @param  list<int>  $grupos
     * @param  list<int>  $aulas  ids de aulas
     * @param  list<int>  $normasMarcadas  ids de plantillas, en el orden en que se muestran
     * @param  list<int>|null  $normasConservadas  ids de `examen_norma` cuya plantilla ya no
     *                                             existe y que siguen en el examen; nulo = todas
     */
    public function __construct(
        public int $asignaturaId,
        public string $tipo,
        public string $fecha,
        public string $horaInicio,
        public int $duracionMinutos,
        public ?string $normas,
        public array $grupos,
        public array $aulas,
        public array $normasMarcadas = [],
        public ?array $normasConservadas = null,
        public ?int $periodoId = null,
    ) {}
}
