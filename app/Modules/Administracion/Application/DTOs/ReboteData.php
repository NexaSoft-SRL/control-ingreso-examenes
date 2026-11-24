<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

use DateTimeImmutable;

/**
 * Un correo que volvio: la direccion no existe o no recibe correos. Lo avisa
 * el servidor de correo con un mensaje al remitente, despues del envio.
 */
final readonly class ReboteData
{
    public function __construct(
        // La direccion que rechazo el correo, en minusculas.
        public string $destinatario,
        // Cuando llego el aviso del rebote.
        public DateTimeImmutable $fecha,
        // El mensaje del aviso en el buzon, para archivarlo una vez leido.
        public string $mensajeId,
    ) {}
}
