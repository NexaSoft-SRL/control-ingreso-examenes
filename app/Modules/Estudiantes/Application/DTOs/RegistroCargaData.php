<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

use App\Modules\Estudiantes\Domain\Enums\AlcanceCarga;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;

/**
 * Todo lo que una carga escribe, ya decidido fila por fila: se guarda de
 * una vez, en una sola transaccion.
 */
final readonly class RegistroCargaData
{
    /**
     * @param  list<FilaInscritoData>  $nuevos  estudiantes que se crean
     * @param  list<FilaInscritoData>  $inscripciones  filas que inscriben (nuevos y reutilizados)
     * @param  list<FilaInscritoData>  $conflictos  filas en espera
     * @param  list<string>  $porVerificar  codigos que la carga de administracion confirma
     */
    public function __construct(
        public AlcanceCarga $alcance,
        public ?int $grupoId,
        public int $facultadId,
        public int $periodoId,
        public OrigenEstudiante $via,
        public int $usuarioId,
        public ResultadoCargaData $resultado,
        public array $nuevos,
        public array $inscripciones,
        public array $conflictos,
        public array $porVerificar,
    ) {}
}
