<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

/**
 * Los videos de evidencia de las incidencias. Se guardan tal como llegan
 * (el navegador ya los graba livianos); el tamano lo limita la validacion.
 */
interface AlmacenVideos
{
    /**
     * Guarda el video con un nombre al azar y devuelve su ruta.
     */
    public function guardar(string $contenido, string $tipo): ?string;

    /**
     * La ruta absoluta del archivo, o nulo si ya no existe.
     */
    public function archivo(string $ruta): ?string;

    public function borrar(string $ruta): void;
}
