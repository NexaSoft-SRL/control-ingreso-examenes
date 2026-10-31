<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * El plazo de fotos de un grupo (`plazos_foto_grupo`): desde y hasta que
 * dia (`AAAA-MM-DD`, incluidos) suben su foto sus inscritos, quien lo fijo
 * y cuantos todavia no la tienen. Sin dias, el grupo no tiene plazo propio.
 */
final readonly class PlazoFotoDeGrupoData
{
    public function __construct(
        public ?string $desde,
        public ?string $hasta,
        public ?string $fijadoPor,
        public int $inscritos,
        public int $sinFoto,
    ) {}

    public function vencidoEl(string $dia): bool
    {
        return $this->hasta !== null && $this->hasta < $dia;
    }

    /**
     * @return array{hasta: string|null, fijado_por: string|null, inscritos: int, sin_foto: int, vencido: bool}
     */
    public function comoArreglo(string $hoy): array
    {
        return [
            'hasta' => $this->hasta,
            'fijado_por' => $this->fijadoPor,
            'inscritos' => $this->inscritos,
            'sin_foto' => $this->sinFoto,
            'vencido' => $this->vencidoEl($hoy),
        ];
    }
}
