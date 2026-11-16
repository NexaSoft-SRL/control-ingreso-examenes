<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Estudiantes\Application\Contracts\CorreoCodigoAcceso;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Application\Contracts\RegistroPendienteGateway;
use App\Modules\Estudiantes\Domain\Models\Estudiante;

/**
 * Envia al correo institucional del estudiante un codigo de seis digitos
 * que vale diez minutos. Si el codigo universitario no esta en el padron
 * lo envia igual, al correo que le corresponde: solo quien lo recibe
 * puede empadronarse con ese codigo, y asi la respuesta no delata quien
 * esta en el padron.
 */
final readonly class SolicitarCodigoDeAcceso
{
    public const MINUTOS = 10;

    public function __construct(
        private PortalEstudianteGateway $portal,
        private RegistroPendienteGateway $pendientes,
        private CorreoCodigoAcceso $correo,
    ) {}

    /**
     * Devuelve el codigo enviado (para el modo de demostracion).
     */
    public function execute(string $codigoUniversitario): string
    {
        $codigoUniversitario = trim($codigoUniversitario);
        $estudiante = $this->portal->porCodigo($codigoUniversitario);
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = hash('sha256', $codigo);

        if ($estudiante === null) {
            $this->pendientes->guardarCodigo($codigoUniversitario, $hash, self::MINUTOS);
            $this->correo->enviar(Estudiante::correoDe($codigoUniversitario), '', $codigo, self::MINUTOS);

            return $codigo;
        }

        $this->portal->guardarCodigo($estudiante->id, $hash, self::MINUTOS);
        $this->correo->enviar($estudiante->correo, $estudiante->nombre, $codigo, self::MINUTOS);

        return $codigo;
    }
}
