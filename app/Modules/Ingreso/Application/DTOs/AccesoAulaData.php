<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Un acceso abierto con el codigo de un aula. `hasta` es el final del
 * examen, en ISO 8601. `estado` es PENDIENTE hasta que el docente lo
 * autoriza en persona; despues AUTORIZADO, RECHAZADO o CORTADO.
 */
final readonly class AccesoAulaData
{
    public function __construct(
        public int $id,
        public int $aulaId,
        public string $aula,
        public int $examenId,
        public string $nombre,
        public string $hasta,
        public string $estado = 'AUTORIZADO',
    ) {}

    public function autorizado(): bool
    {
        return $this->estado === 'AUTORIZADO';
    }

    public function comoOperador(): OperadorData
    {
        return new OperadorData(
            usuarioId: null,
            accesoAulaId: $this->id,
            aulaId: $this->aulaId,
            examenId: $this->examenId,
            aula: $this->aula,
            nombre: $this->nombre,
            hasta: $this->hasta,
        );
    }

    /**
     * @return array{aula_id: int, aula: string, examen_id: int, nombre: string, hasta: string, estado: string}
     */
    public function comoArreglo(): array
    {
        return [
            'aula_id' => $this->aulaId,
            'aula' => $this->aula,
            'examen_id' => $this->examenId,
            'nombre' => $this->nombre,
            'hasta' => $this->hasta,
            'estado' => mb_strtolower($this->estado),
        ];
    }
}
