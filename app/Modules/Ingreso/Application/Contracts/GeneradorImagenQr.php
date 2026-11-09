<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

/**
 * Dibuja el codigo QR. La imagen codifica solo el token opaco.
 */
interface GeneradorImagenQr
{
    /**
     * La imagen lista para incrustar en una pagina, como URI de datos.
     */
    public function comoUriDeDatos(string $token): string;

    /**
     * Los bytes del PNG, para adjuntarlo a un correo.
     */
    public function comoPng(string $token): string;
}
