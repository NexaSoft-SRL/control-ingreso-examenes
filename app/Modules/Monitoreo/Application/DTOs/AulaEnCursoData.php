<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * La situacion de un aula mientras corre el examen.
 */
final readonly class AulaEnCursoData
{
    /**
     * @param  list<string>  $apoyo
     */
    public function __construct(
        public int $aulaId,
        public string $nombre,
        public ?int $edificioId,
        public string $encargado,
        public bool $encargadoEsDocente,
        public array $apoyo,
        public int $ingresados,
        public int $asignados,
        public int $incidenciasSinAtender,
        public ?string $compartida,
        public bool $usaCodigo,
        public bool $aMiCargo,
        public ?string $ultimoIngreso = null,
        // Asignados a esta aula que todavia no entraron (por ninguna puerta):
        // la misma cifra que la lista del aula.
        public ?int $faltan = null,
        // Entraron aqui siendo de otra aula / siendo de esta, entraron por otra.
        public int $deOtra = 0,
        public int $enOtra = 0,
    ) {}
}
