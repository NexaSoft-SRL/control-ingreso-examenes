<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Files;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Las imagenes en el disco privado (`storage/app/private/imagenes`),
 * reducidas con GD. Ninguna se sirve como archivo publico.
 */
final class AlmacenImagenesEnDisco implements AlmacenImagenes
{
    private const RAIZ = 'imagenes';

    private const CALIDAD = 82;

    public function guardar(string $carpeta, string $nombre, string $contenido, int $lado): ?string
    {
        $original = @imagecreatefromstring($contenido);

        if (! $original instanceof GdImage) {
            return null;
        }

        $ancho = imagesx($original);
        $alto = imagesy($original);
        $escala = min(1, $lado / max($ancho, $alto, 1));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $reducida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        // El fondo blanco evita que una transparencia salga negra en JPEG.
        imagefill($reducida, 0, 0, (int) imagecolorallocate($reducida, 255, 255, 255));
        imagecopyresampled($reducida, $original, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        imagejpeg($reducida, null, self::CALIDAD);
        $jpeg = (string) ob_get_clean();

        $ruta = sprintf('%s/%s/%s.jpg', self::RAIZ, trim($carpeta, '/'), $nombre);

        return Storage::disk('local')->put($ruta, $jpeg) ? $ruta : null;
    }

    public function leer(string $ruta): ?string
    {
        $disco = Storage::disk('local');

        return $disco->exists($ruta) ? $disco->get($ruta) : null;
    }

    public function borrar(string $ruta): void
    {
        Storage::disk('local')->delete($ruta);
    }
}
