<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\CredencialesGateway;

final readonly class CambiarContrasena
{
    public function __construct(private CredencialesGateway $gateway) {}

    public function execute(int $usuarioId, string $actual, string $nueva): bool
    {
        return $this->gateway->cambiar($usuarioId, $actual, $nueva);
    }
}
