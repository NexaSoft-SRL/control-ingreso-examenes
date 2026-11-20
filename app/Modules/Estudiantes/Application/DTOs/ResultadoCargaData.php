<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Lo que dejo una carga: cada fila del archivo cae en una sola de las
 * cinco salidas.
 */
final readonly class ResultadoCargaData
{
    /**
     * @param  list<RechazoCargaData>  $rechazos
     * @param  list<ConflictoCargaData>  $conflictos
     */
    public function __construct(
        public string $archivo,
        public int $filas,
        public int $nuevos,
        public int $reutilizados,
        public int $yaInscritos,
        public array $rechazos,
        public array $conflictos,
        public ?string $confirmacion = null,
    ) {}

    public function conConfirmacion(string $confirmacion): self
    {
        return new self(
            archivo: $this->archivo,
            filas: $this->filas,
            nuevos: $this->nuevos,
            reutilizados: $this->reutilizados,
            yaInscritos: $this->yaInscritos,
            rechazos: $this->rechazos,
            conflictos: $this->conflictos,
            confirmacion: $confirmacion,
        );
    }
}
