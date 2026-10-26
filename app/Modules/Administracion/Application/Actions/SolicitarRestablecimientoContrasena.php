<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\CredencialesGateway;

final readonly class SolicitarRestablecimientoContrasena
{
    public function __construct(private CredencialesGateway $gateway) {}

    public function execute(string $usuario): void
    {
        $this->gateway->solicitarRestablecimiento($usuario);
    }
}
