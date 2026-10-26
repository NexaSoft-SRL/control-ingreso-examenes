<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Un correo que el sistema esta por enviar. `tipo` es una de las
 * constantes; los identificadores son el contexto de ese correo.
 */
final readonly class RegistroCorreoData
{
    public const TIPO_CODIGO_QR = 'CODIGO_QR';

    public const TIPO_AVISO = 'AVISO';

    public function __construct(
        public string $tipo,
        public string $destinatario,
        public ?int $examenId = null,
        public ?int $estudianteId = null,
        public ?int $solicitadoPor = null,
    ) {}
}
