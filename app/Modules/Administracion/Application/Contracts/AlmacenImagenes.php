<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

/**
 * Las imagenes que suben las personas (la foto del estudiante, la
 * evidencia de una incidencia). Se guardan siempre como JPEG reducido:
 * el tamano lo controla el sistema, no quien sube.
 */
interface AlmacenImagenes
{
    /**
     * Reduce la imagen a `$lado` pixeles en su lado mayor y la guarda en
     * `$carpeta` con ese `$nombre`. Devuelve la ruta, o nulo si el
     * contenido no es una imagen que se pueda leer.
     */
    public function guardar(string $carpeta, string $nombre, string $contenido, int $lado): ?string;

    /**
     * Los bytes del JPEG guardado, o nulo si ya no existe.
     */
    public function leer(string $ruta): ?string;

    public function borrar(string $ruta): void;
}
