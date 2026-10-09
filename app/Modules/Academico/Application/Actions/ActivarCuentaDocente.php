<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Actions;

use App\Modules\Academico\Application\Contracts\CuentaDocenteGateway;
use App\Modules\Academico\Domain\Exceptions\DatoDeCuentaEnUsoException;
use App\Modules\Academico\Domain\Exceptions\DocenteYaTieneCuentaException;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;

/**
 * Da una cuenta con rol Docente a un docente de la oferta y los une.
 */
final readonly class ActivarCuentaDocente
{
    public function __construct(
        private CuentaDocenteGateway $docentes,
        private CuentaUsuarioGateway $cuentas,
    ) {}

    /**
     * Devuelve null si el docente no existe. La contrasena temporal viaja
     * solo en este resultado.
     *
     * @throws DocenteYaTieneCuentaException
     * @throws DatoDeCuentaEnUsoException
     */
    public function execute(int $docenteId, string $usuario, ?string $correo, ?int $autorId): ?CuentaCreadaData
    {
        $docente = $this->docentes->docente($docenteId);

        if ($docente === null) {
            return null;
        }

        if ($docente['tiene_cuenta']) {
            throw new DocenteYaTieneCuentaException;
        }

        if ($this->cuentas->usuarioRegistrado($usuario)) {
            throw DatoDeCuentaEnUsoException::usuario();
        }

        if ($correo !== null && $this->cuentas->correoRegistrado($correo)) {
            throw DatoDeCuentaEnUsoException::correo();
        }

        return $this->docentes->activar(
            $docenteId,
            new NuevaCuentaData(
                nombre: $docente['nombre'],
                usuario: $usuario,
                correo: $correo,
                rol: NuevaCuentaData::ROL_DOCENTE,
            ),
            $autorId,
        );
    }
}
