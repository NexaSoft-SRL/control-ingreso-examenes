<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\ConsultaUsuariosGateway;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\Transaccion;
use App\Modules\Administracion\Domain\Exceptions\BloqueoPropioException;

/**
 * Bloqueo y desbloqueo de una cuenta. La cuenta no se borra: queda sin
 * poder iniciar sesion y su historial en la bitacora se conserva.
 */
final readonly class CambiarEstadoCuenta
{
    public function __construct(
        private CuentaUsuarioGateway $cuentas,
        private ConsultaUsuariosGateway $consulta,
        private BitacoraGateway $bitacora,
        private Transaccion $transaccion,
    ) {}

    /**
     * Devuelve false si la cuenta no existe.
     *
     * @throws BloqueoPropioException
     */
    public function execute(
        int $usuarioId,
        bool $activo,
        ?int $administradorId,
    ): bool {
        if (! $activo && $usuarioId === $administradorId) {
            throw new BloqueoPropioException('No puedes bloquear tu propia cuenta.');
        }

        $cuenta = $this->consulta->buscar($usuarioId);

        if ($cuenta === null) {
            return false;
        }

        return $this->transaccion->ejecutar(function () use (
            $usuarioId,
            $activo,
            $administradorId,
            $cuenta,
        ): bool {
            if (! $this->cuentas->cambiarEstado($usuarioId, $activo)) {
                return false;
            }

            $this->bitacora->registrar(
                $administradorId,
                $activo ? 'usuario.desbloquear' : 'usuario.bloquear',
                'usuarios',
                $usuarioId,
                $activo
                    ? "Cuenta {$cuenta->usuario} desbloqueada."
                    : "Cuenta {$cuenta->usuario} bloqueada.",
            );

            return true;
        });
    }
}
