<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

interface CredencialesGateway
{
    public function cambiar(int $usuarioId, string $contrasenaActual, string $nuevaContrasena): bool;

    public function solicitarRestablecimiento(string $usuario): void;

    public function restablecer(string $correo, string $token, string $contrasena): bool;
}
