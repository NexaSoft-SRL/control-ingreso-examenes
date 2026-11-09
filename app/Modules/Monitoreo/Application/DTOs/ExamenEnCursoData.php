<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Un examen del selector de «En curso», con las aulas que la cuenta puede seguir.
 */
final readonly class ExamenEnCursoData
{
    /**
     * @param  list<AulaSeguidaData>  $aulas
     */
    public function __construct(
        public int $examenId,
        public string $texto,
        public string $asignatura,
        public string $fecha,
        public string $hora,
        public bool $abierto,
        public array $aulas,
        public string $tipo = '',
        public string $tipoTexto = '',
    ) {}
}
