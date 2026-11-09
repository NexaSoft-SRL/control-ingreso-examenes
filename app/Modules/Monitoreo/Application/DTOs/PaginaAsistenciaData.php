<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Una pagina de la lista del aula con las cifras del aula entera.
 */
final readonly class PaginaAsistenciaData
{
    /**
     * @param  list<FilaAsistenciaData>  $filas
     */
    public function __construct(
        public int $ingresaron,
        public int $faltan,
        public int $asignados,
        public array $filas,
        public int $total,
        // Para que las cifras cuadren: asignados aqui que entraron por otra
        // puerta, y quienes entraron aqui estando asignados a otra aula.
        public int $enOtraAula = 0,
        public int $deOtraAula = 0,
    ) {}
}
