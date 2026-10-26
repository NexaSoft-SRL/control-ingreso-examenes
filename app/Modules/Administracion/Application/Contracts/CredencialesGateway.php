<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Domain\Exceptions\ContrasenaRepetidaException;

interface CredencialesGateway
{
    /**
     * Sin la contrasena actual (null) solo cambia una cuenta con contrasena
     * temporal.
     *
     * @throws ContrasenaRepetidaException
     */
    public function cambiar(int $usuarioId, ?string $contrasenaActual, string $nuevaContrasena): bool;

    public function solicitarRestablecimiento(string $usuario): void;

    public function restablecer(string $correo, string $token, string $contrasena): bool;
}
