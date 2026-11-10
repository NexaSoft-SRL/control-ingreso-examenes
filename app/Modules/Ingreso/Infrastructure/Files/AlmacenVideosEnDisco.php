<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Files;

use App\Modules\Ingreso\Application\Contracts\AlmacenVideos;
use Illuminate\Support\Facades\Storage;

final class AlmacenVideosEnDisco implements AlmacenVideos
{
    private const EXTENSIONES = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];

    public function guardar(string $contenido, string $tipo): ?string
    {
        $extension = self::EXTENSIONES[$tipo] ?? null;

        if ($extension === null || $contenido === '') {
            return null;
        }

        $ruta = sprintf('incidencias/videos/%s.%s', bin2hex(random_bytes(16)), $extension);

        return Storage::disk('local')->put($ruta, $contenido) ? $ruta : null;
    }

    public function archivo(string $ruta): ?string
    {
        $disco = Storage::disk('local');

        return $disco->exists($ruta) ? $disco->path($ruta) : null;
    }

    public function borrar(string $ruta): void
    {
        Storage::disk('local')->delete($ruta);
    }
}
