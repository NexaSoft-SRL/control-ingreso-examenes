<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Un correo que el sistema esta por enviar. `tipo` es una de las tres
 * constantes; los identificadores son el contexto que tenga ese correo.
 */
final readonly class RegistroCorreoData
{
    public const TIPO_CREDENCIALES = 'CREDENCIALES';

    public const TIPO_RESTABLECIMIENTO = 'RESTABLECIMIENTO';

    public const TIPO_CODIGO_QR = 'CODIGO_QR';

    /**
     * @var list<string>
     */
    public const TIPOS = [
        self::TIPO_CREDENCIALES,
        self::TIPO_RESTABLECIMIENTO,
        self::TIPO_CODIGO_QR,
    ];

    public function __construct(
        public string $tipo,
        public string $destinatario,
        // Cuenta destinataria (credenciales, restablecimiento).
        public ?int $usuarioId = null,
        public ?int $examenId = null,
        public ?int $estudianteId = null,
        public ?int $solicitadoPor = null,
    ) {}
}
