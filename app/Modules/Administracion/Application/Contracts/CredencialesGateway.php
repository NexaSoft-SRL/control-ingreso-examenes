<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

interface CredencialesGateway
{
    public function contrasenaCoincide(
        int $usuarioId,
        string $contrasena,
    ): bool;

    /**
     * Guarda la contrasena cifrada, deja constancia de que el usuario ya
     * definio la suya y retira el vencimiento de la temporal.
     */
    public function cambiarContrasena(
        int $usuarioId,
        string $nuevaContrasena,
    ): void;

    /**
     * Verdadero mientras la cuenta siga con la contrasena temporal que se
     * le entrego.
     */
    public function debeCambiarContrasena(int $usuarioId): bool;
}
