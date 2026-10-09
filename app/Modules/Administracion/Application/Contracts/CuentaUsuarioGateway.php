<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;

/**
 * Cuentas de acceso. Es el contrato que usan los demas modulos para dar
 * una cuenta a un docente (Academico).
 */
interface CuentaUsuarioGateway
{
    /**
     * Crea la cuenta con una contrasena temporal y la fecha en que vence
     * (el vencimiento y el cambio obligatorio se aplican desde HU-16). La
     * cuenta y su asiento `usuario.registrar` se escriben en una misma
     * transaccion: o quedan los dos o ninguno. Si la cuenta tiene correo,
     * la contrasena ademas viaja ahi. El usuario y el correo se guardan
     * en minusculas.
     *
     * Quien llama valida antes que el usuario y el correo esten libres y
     * que el rol exista.
     */
    public function crear(NuevaCuentaData $datos, ?int $autorId): CuentaCreadaData;

    /**
     * Emite una contrasena temporal nueva para una cuenta que ya existe:
     * la deja otra vez como temporal y levanta el bloqueo por intentos. Asienta `usuario.restablecer_temporal` en la misma
     * transaccion. Devuelve null si la cuenta no existe.
     */
    public function emitirTemporal(int $usuarioId, ?int $autorId): ?CuentaCreadaData;

    public function usuarioRegistrado(string $usuario, ?int $exceptoId = null): bool;

    public function correoRegistrado(string $correo, ?int $exceptoId = null): bool;

    /**
     * Cambia los datos de la cuenta. Devuelve false si la cuenta no
     * existe.
     */
    public function actualizar(
        int $usuarioId,
        string $nombre,
        string $usuario,
        ?string $correo,
        string $rol,
    ): bool;

    /**
     * Habilita o deshabilita el acceso de la cuenta, sin borrarla.
     * Devuelve false si la cuenta no existe.
     */
    public function cambiarEstado(int $usuarioId, bool $activo): bool;
}
