<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AjustesDeRespaldoGateway;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\DTOs\AjustesDeRespaldoData;

/**
 * Cambia como se hace el respaldo automatico. Si algo cambio, queda en la
 * bitacora con el valor anterior y el nuevo.
 */
final readonly class GuardarAjustesDeRespaldo
{
    public function __construct(
        private AjustesDeRespaldoGateway $ajustes,
        private BitacoraGateway $bitacora,
    ) {}

    public function execute(int $usuarioId, AjustesDeRespaldoData $nuevos): void
    {
        $antes = $this->ajustes->leer();
        $cambios = [];

        if ($antes->habilitado !== $nuevos->habilitado) {
            $cambios[] = sprintf('Automático: %s → %s', $antes->habilitado ? 'sí' : 'no', $nuevos->habilitado ? 'sí' : 'no');
        }

        if ($antes->hora !== $nuevos->hora) {
            $cambios[] = sprintf('Hora: %s → %s', $antes->hora, $nuevos->hora);
        }

        if ($antes->reintentoMinutos !== $nuevos->reintentoMinutos) {
            $cambios[] = sprintf('Reintento: %d → %d min', $antes->reintentoMinutos, $nuevos->reintentoMinutos);
        }

        $this->ajustes->guardar($nuevos);

        if ($cambios !== []) {
            $this->bitacora->registrar(
                $usuarioId,
                'respaldo.ajustes',
                'ajustes',
                null,
                'Respaldo automático. '.implode('; ', $cambios).'.',
            );
        }
    }
}
