<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class RespaldoData
{
    public function __construct(
        public int $id,
        public string $archivo,
        public string $tipo,
        public int $tamanoBytes,
        public string $estado,
        public ?string $error,
        public ?string $versionEsquema,
        public ?int $usuarioId,
        public string $fecha,
        public string $createdAt,
    ) {}
}
