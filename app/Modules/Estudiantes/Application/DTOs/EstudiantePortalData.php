<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * El estudiante que entro con su codigo: lo que muestra su pantalla.
 */
final readonly class EstudiantePortalData
{
    public function __construct(
        public int $id,
        public string $codigo,
        public string $nombre,
        public string $correo,
        public bool $conFoto,
    ) {}

    /**
     * @return array{id: int, codigo: string, nombre: string, correo: string, con_foto: bool}
     */
    public function comoArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'correo' => $this->correo,
            'con_foto' => $this->conFoto,
        ];
    }
}
