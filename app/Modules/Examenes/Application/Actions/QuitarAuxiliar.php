<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AuxiliarGateway;
use App\Modules\Examenes\Domain\Exceptions\AuxiliarAsignadoException;

/**
 * Saca a un auxiliar de la lista del docente. No borra la cuenta ni lo que
 * ya registro; solo deja de figurar entre «los suyos».
 */
final readonly class QuitarAuxiliar
{
    public function __construct(
        private AuxiliarGateway $auxiliares,
    ) {}

    /**
     * Falso si la cuenta no esta en la lista del docente.
     *
     * @throws AuxiliarAsignadoException
     */
    public function execute(int $cuentaId, int $usuarioId): bool
    {
        if ($this->auxiliares->atiendeExamenPendiente($cuentaId, $usuarioId)) {
            throw new AuxiliarAsignadoException;
        }

        return $this->auxiliares->quitar($cuentaId, $usuarioId);
    }
}
