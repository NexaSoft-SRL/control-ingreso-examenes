<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

/**
 * Dibuja la imagen del codigo QR dentro del sistema, sin servicios
 * externos. La imagen lleva solo el contenido cifrado del codigo.
 */
interface GeneradorImagenQr
{
    /**
     * La imagen lista para incrustar en una pagina, como URI de datos.
     */
    public function comoUriDeDatos(string $contenido): string;

    /**
     * Los bytes del PNG, para el correo.
     */
    public function comoPng(string $contenido): string;
}
