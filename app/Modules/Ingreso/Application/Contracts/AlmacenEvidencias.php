<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

/**
 * Las fotos que respaldan una incidencia. Se guardan siempre como JPEG
 * reducido en el disco privado: el tamano lo controla el sistema, no
 * quien sube.
 */
interface AlmacenEvidencias
{
    /**
     * Devuelve la ruta, o nulo si el contenido no es una imagen legible.
     */
    public function guardar(string $contenido): ?string;

    /**
     * Los bytes del JPEG guardado, o nulo si ya no existe.
     */
    public function leer(string $ruta): ?string;

    public function borrar(string $ruta): void;
}
