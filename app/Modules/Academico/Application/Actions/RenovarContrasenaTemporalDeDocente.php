<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Actions;

use App\Modules\Academico\Application\Contracts\CuentaDocenteGateway;
use App\Modules\Academico\Domain\Exceptions\ContrasenaTemporalNoAplicaException;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;

/**
 * Emite una contraseña temporal nueva a un docente cuya temporal no usó, se
 * perdió o venció. Es la misma emisión del restablecimiento de Usuarios y
 * roles (la anterior deja de servir, se pide cambiarla al entrar, se envía por
 * correo si hay y queda en la bitácora), pero se pide desde la pantalla
 * Docentes y solo vale mientras la cuenta siga con contraseña temporal.
 */
final readonly class RenovarContrasenaTemporalDeDocente
{
    public function __construct(
        private CuentaDocenteGateway $docentes,
        private CuentaUsuarioGateway $cuentas,
    ) {}

    /**
     * Devuelve null si el docente no existe. La contraseña viaja solo en
     * este resultado.
     *
     * @throws ContrasenaTemporalNoAplicaException
     */
    public function execute(int $docenteId, ?int $autorId): ?CuentaCreadaData
    {
        if ($this->docentes->docente($docenteId) === null) {
            return null;
        }

        $cuenta = $this->docentes->cuenta($docenteId);

        if ($cuenta === null) {
            throw ContrasenaTemporalNoAplicaException::sinCuenta();
        }

        if (! $cuenta['activa']) {
            throw ContrasenaTemporalNoAplicaException::bloqueada();
        }

        if (! $cuenta['temporal']) {
            throw ContrasenaTemporalNoAplicaException::contrasenaPropia();
        }

        return $this->cuentas->emitirTemporal($cuenta['usuario_id'], $autorId);
    }
}
