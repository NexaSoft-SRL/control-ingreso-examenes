<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\ControlPuertaGateway;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;

/**
 * Lo que el docente reacomoda con el examen en marcha: una persona que
 * atiende pasa a otra aula, o los estudiantes de un aula que no se pudo
 * usar pasan a otra. Queda en la bitacora.
 */
final readonly class MoverEnElExamen
{
    public function __construct(
        private AlcancePuesto $alcance,
        private ControlPuertaGateway $control,
    ) {}

    /**
     * Mueve a una persona (`$usuarioId`) o, sin ella, a los estudiantes.
     * Devuelve cuantos estudiantes se movieron (1 para una persona).
     *
     * @throws DatoNoValidoException
     */
    public function execute(int $examenId, int $deAulaId, int $aAulaId, int $autorId, ?int $usuarioId = null): int
    {
        $this->alcance->exigirDocente($autorId, $examenId);

        if ($deAulaId === $aAulaId) {
            throw new DatoNoValidoException('a_aula_id', 'Elija otra aula.');
        }

        if ($usuarioId !== null) {
            $resultado = $this->control->trasladarPersona($examenId, $usuarioId, $deAulaId, $aAulaId, $autorId);

            return match ($resultado) {
                'OK' => 1,
                'NO_ESTA' => throw new DatoNoValidoException('usuario_id', 'No atiende esa aula.'),
                default => throw new DatoNoValidoException('a_aula_id', 'El aula no es de este examen.'),
            };
        }

        return $this->control->trasladarEstudiantes($examenId, $deAulaId, $aAulaId, $autorId)
            ?? throw new DatoNoValidoException('a_aula_id', 'El aula no es de este examen.');
    }
}
