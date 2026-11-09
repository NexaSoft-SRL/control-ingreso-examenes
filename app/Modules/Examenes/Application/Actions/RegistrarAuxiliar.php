<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use App\Modules\Examenes\Application\Contracts\AuxiliarGateway;
use App\Modules\Examenes\Application\DTOs\AuxiliarData;
use App\Modules\Examenes\Domain\Exceptions\AuxiliarYaRegistradoException;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;

/**
 * «Auxiliar nuevo»: el auxiliar es un estudiante, asi que su correo sale de
 * su codigo universitario. Se le crea la cuenta con rol Auxiliar y la
 * contrasena temporal se le envia a ese correo.
 */
final readonly class RegistrarAuxiliar
{
    public function __construct(
        private AuxiliarGateway $auxiliares,
        private CuentaUsuarioGateway $cuentas,
        private ProponedorUsuario $proponedor,
        /** `config('umss.dominio_correo_estudiantes')`: codigo universitario + este dominio. */
        private string $dominioCorreo,
    ) {}

    /**
     * @throws AuxiliarYaRegistradoException
     * @throws DatoInvalidoException
     */
    public function execute(string $nombre, string $codigoUniversitario, int $usuarioId): AuxiliarData
    {
        $existente = $this->auxiliares->porCodigo($codigoUniversitario, $usuarioId);

        if ($existente !== null) {
            throw new AuxiliarYaRegistradoException(
                $existente->id,
                $existente->nombre,
                $existente->usuario,
                $existente->mio,
            );
        }

        $correo = $codigoUniversitario.'@'.$this->dominioCorreo;

        if ($this->cuentas->correoRegistrado($correo)) {
            throw new DatoInvalidoException('codigo_universitario', 'Ya existe');
        }

        $cuenta = $this->cuentas->crear(
            new NuevaCuentaData(
                nombre: $nombre,
                usuario: $this->proponedor->proponer($nombre),
                correo: $correo,
                rol: NuevaCuentaData::ROL_AUXILIAR,
            ),
            $usuarioId,
        );

        $this->auxiliares->registrar($cuenta->id, $codigoUniversitario, $usuarioId);

        return new AuxiliarData(
            id: $cuenta->id,
            nombre: $nombre,
            usuario: $cuenta->usuario,
            mio: true,
        );
    }
}
