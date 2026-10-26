<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

interface CorreoCodigoAcceso
{
    public function enviar(string $correo, string $nombre, string $codigo, int $minutos): void;
}
