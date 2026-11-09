<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

interface RestablecimientoGateway
{
    /**
     * Genera un enlace de un solo uso y lo envia al correo de contacto de
     * la cuenta. Devuelve el identificador del usuario, o null si el
     * usuario no corresponde a ninguna cuenta activa con correo.
     */
    public function solicitar(string $usuario): ?int;

    /**
     * Cambia la contrasena si el token es valido y no ha vencido, y lo
     * invalida. Devuelve el identificador del usuario, o null si el token
     * no sirve.
     */
    public function restablecer(
        string $usuario,
        string $token,
        string $contrasenaNueva,
    ): ?int;
}
