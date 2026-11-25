<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\AnulacionIngresoGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Domain\Exceptions\AnulacionFueraDeDiaException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Domain\Exceptions\IngresoYaAnuladoException;

/**
 * Corrige un ingreso hecho por error: el registro original no se toca; se
 * asienta una anulación con su motivo y el estudiante deja de contar como
 * ingresado, de modo que puede volver a pasar por la puerta. La hace el
 * docente del examen, el mismo día.
 */
final readonly class AnularIngreso
{
    public function __construct(
        private AnulacionIngresoGateway $anulaciones,
        private PuertaGateway $puerta,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * Falso si no existe el ingreso.
     *
     * @throws FueraDeAlcanceException
     * @throws IngresoYaAnuladoException
     * @throws AnulacionFueraDeDiaException
     */
    public function execute(int $ingresoId, string $motivo, int $usuarioId): bool
    {
        $ingreso = $this->anulaciones->ingreso($ingresoId);

        if ($ingreso === null) {
            return false;
        }

        $this->alcance->exigirDocente($usuarioId, $ingreso->examenId);

        if ($ingreso->anulado) {
            throw new IngresoYaAnuladoException;
        }

        $examen = $this->puerta->examen($ingreso->examenId);

        if ($examen === null || ! $examen->esHoy) {
            throw new AnulacionFueraDeDiaException;
        }

        if (! $this->anulaciones->anular($ingreso->id, $motivo, $usuarioId)) {
            throw new IngresoYaAnuladoException;
        }

        return true;
    }
}
