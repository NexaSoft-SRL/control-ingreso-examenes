<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Mail;

use App\Modules\Estudiantes\Application\Contracts\CorreoCodigoAcceso;
use Illuminate\Support\Facades\Mail;

final class CorreoCodigoAccesoMail implements CorreoCodigoAcceso
{
    public function enviar(string $correo, string $nombre, string $codigo, int $minutos): void
    {
        Mail::to($correo)->send(new CodigoAccesoMail($nombre, $codigo, $minutos));
    }
}
