<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\ConsultaUsuariosGateway;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\Transaccion;
use App\Modules\Administracion\Application\DTOs\ActualizarCuentaData;
use App\Modules\Administracion\Application\DTOs\CuentaActualizadaData;
use App\Modules\Administracion\Domain\Exceptions\BloqueoPropioException;

/**
 * Edicion de una cuenta desde la pantalla de usuarios: sus datos, su rol
 * y, si viaja, su bloqueo. La contrasena no se toca aqui: la define su
 * dueno o se restablece con una temporal.
 */
final readonly class ActualizarCuentaUsuario
{
    public function __construct(
        private CuentaUsuarioGateway $cuentas,
        private ConsultaUsuariosGateway $consulta,
        private CambiarEstadoCuenta $cambiarEstado,
        private BitacoraGateway $bitacora,
        private Transaccion $transaccion,
    ) {}

    /**
     * Devuelve null si la cuenta no existe.
     *
     * @throws BloqueoPropioException
     */
    public function execute(
        ActualizarCuentaData $datos,
        ?int $administradorId,
    ): ?CuentaActualizadaData {
        $antes = $this->consulta->buscar($datos->usuarioId);

        if ($antes === null) {
            return null;
        }

        $usuario = mb_strtolower(trim($datos->usuario));
        $cambiaEstado = $datos->activo !== null && $datos->activo !== $antes->activo;

        // Se comprueba antes de escribir nada: una edicion que ademas
        // intenta el bloqueo propio no deja los datos a medio guardar.
        if ($cambiaEstado && $datos->activo === false && $datos->usuarioId === $administradorId) {
            throw new BloqueoPropioException('No puedes bloquear tu propia cuenta.');
        }

        $cambianDatos = $antes->nombre !== $datos->nombre
            || $antes->usuario !== $usuario
            || $antes->correo !== $datos->correo
            || $antes->rol !== $datos->rol;

        $this->transaccion->ejecutar(function () use (
            $datos,
            $administradorId,
            $usuario,
            $cambianDatos,
            $cambiaEstado,
        ): void {
            if ($cambianDatos) {
                $this->cuentas->actualizar(
                    $datos->usuarioId,
                    $datos->nombre,
                    $usuario,
                    $datos->correo,
                    $datos->rol,
                );

                $this->bitacora->registrar(
                    $administradorId,
                    'usuario.actualizar',
                    'usuarios',
                    $datos->usuarioId,
                    "Cuenta {$usuario} actualizada con el rol {$datos->rol}.",
                );
            }

            if ($cambiaEstado && $datos->activo !== null) {
                $this->cambiarEstado->execute(
                    $datos->usuarioId,
                    $datos->activo,
                    $administradorId,
                );
            }
        });

        $despues = $this->consulta->buscar($datos->usuarioId);

        if ($despues === null) {
            return null;
        }

        return new CuentaActualizadaData(
            cuenta: $despues,
            estadoCambiadoA: $cambiaEstado ? $datos->activo : null,
        );
    }
}
