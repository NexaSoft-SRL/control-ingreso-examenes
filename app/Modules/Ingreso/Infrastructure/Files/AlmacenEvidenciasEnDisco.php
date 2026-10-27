<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Files;

use App\Modules\Ingreso\Application\Contracts\AlmacenEvidencias;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Las evidencias en el disco privado (`storage/app/private/imagenes/
 * incidencias`), reducidas con GD a 1280 px en su lado mayor. Ninguna se
 * sirve como archivo publico: se leen con la sesion.
 */
final class AlmacenEvidenciasEnDisco implements AlmacenEvidencias
{
    private const CARPETA = 'imagenes/incidencias';

    private const LADO = 1280;

    private const CALIDAD = 82;

    public function guardar(string $contenido): ?string
    {
        $original = @imagecreatefromstring($contenido);

        if (! $original instanceof GdImage) {
            return null;
        }

        $ancho = imagesx($original);
        $alto = imagesy($original);
        $escala = min(1, self::LADO / max($ancho, $alto, 1));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $reducida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        // El fondo blanco evita que una transparencia salga negra en JPEG.
        imagefill($reducida, 0, 0, (int) imagecolorallocate($reducida, 255, 255, 255));
        imagecopyresampled($reducida, $original, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        imagejpeg($reducida, null, self::CALIDAD);
        $jpeg = (string) ob_get_clean();

        $ruta = sprintf('%s/%s.jpg', self::CARPETA, bin2hex(random_bytes(16)));

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
