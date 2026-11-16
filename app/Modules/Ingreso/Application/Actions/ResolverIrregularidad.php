<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\SinConexionGateway;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;

/**
 * El docente del examen resuelve una irregularidad del modo sin conexion:
 * la confirma (lo ocurrido es valido) o la descarta (error del puesto o de
 * la instantanea), siempre con una nota. Queda en la bitacora.
 */
final readonly class ResolverIrregularidad
{
    public const ESTADOS = ['CONFIRMADA', 'DESCARTADA'];

    public function __construct(
        private SinConexionGateway $sinConexion,
        private AlcancePuesto $alcance,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * @return bool falso si no existe
     *
     * @throws FueraDeAlcanceException
     * @throws DatoNoValidoException
     */
    public function execute(int $irregularidadId, string $estado, string $nota, int $usuarioId): bool
    {
        $examenId = $this->sinConexion->examenDeIrregularidad($irregularidadId);

        if ($examenId === null) {
            return false;
        }

        $this->alcance->exigirDocente($usuarioId, $examenId);

        if (! in_array($estado, self::ESTADOS, true) || mb_strlen(trim($nota)) < 5) {
            throw new DatoNoValidoException('nota', 'Mínimo 5 caracteres');
        }

        if (! $this->sinConexion->resolver($irregularidadId, $usuarioId, $estado, trim($nota))) {
            throw new DatoNoValidoException('irregularidad', 'Ya estaba resuelta.');
        }

        $this->bitacora->registrar(
            $usuarioId,
            'sin_conexion.resolver',
            'irregularidades_sin_conexion',
            $irregularidadId,
            sprintf('Irregularidad %d del examen %d %s: %s', $irregularidadId, $examenId, $estado === 'CONFIRMADA' ? 'confirmada' : 'descartada', trim($nota)),
        );

        return true;
    }
}
