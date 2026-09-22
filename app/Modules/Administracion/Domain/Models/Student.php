<?php

namespace App\Modules\Administracion\Domain\Models;

class Student
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $apellido,
        public string $ci,
        public string $correo,
        public bool $activo = true,
    ) {}
}
