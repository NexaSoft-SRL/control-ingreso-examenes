<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\CredencialesGateway;

final readonly class RestablecerContrasena
{
    public function __construct(private CredencialesGateway $gateway) {}

    public function execute(string $correo, string $token, string $contrasena): bool
    {
        return $this->gateway->restablecer($correo, $token, $contrasena);
    }
}
