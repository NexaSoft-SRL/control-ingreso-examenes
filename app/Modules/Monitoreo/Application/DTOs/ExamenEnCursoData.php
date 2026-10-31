<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Un examen del selector de «En curso». `fin` es la hora prevista de
 * termino ('HH:MM'); `excedido`, que esa hora ya paso y nadie lo finalizo.
 */
final readonly class ExamenEnCursoData
{
    public function __construct(
        public int $id,
        public string $fecha,
        public string $hora,
        public int $duracion,
        public string $tipo,
        public string $tipoTexto,
        public string $asignatura,
        public bool $abierto,
        public string $fin = '',
        public bool $excedido = false,
    ) {}
}
