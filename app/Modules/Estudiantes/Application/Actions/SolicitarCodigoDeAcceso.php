<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Estudiantes\Application\Contracts\CorreoCodigoAcceso;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;

/**
 * Envia al correo institucional del estudiante un codigo de seis digitos
 * que vale diez minutos. Si el codigo universitario no esta en el padron
 * no envia nada, pero responde igual: asi no se averigua quien esta.
 */
final readonly class SolicitarCodigoDeAcceso
{
    public const MINUTOS = 10;

    public function __construct(
        private PortalEstudianteGateway $portal,
        private CorreoCodigoAcceso $correo,
    ) {}

    /**
     * Devuelve el codigo enviado (para el modo de demostracion), o nulo si
     * no habia a quien enviarlo.
     */
    public function execute(string $codigoUniversitario): ?string
    {
        $estudiante = $this->portal->porCodigo($codigoUniversitario);

        if ($estudiante === null) {
            return null;
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->portal->guardarCodigo($estudiante->id, hash('sha256', $codigo), self::MINUTOS);
        $this->correo->enviar($estudiante->correo, $estudiante->nombre, $codigo, self::MINUTOS);

        return $codigo;
    }
}
