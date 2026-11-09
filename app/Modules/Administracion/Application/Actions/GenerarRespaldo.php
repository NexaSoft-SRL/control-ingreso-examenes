<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\RespaldoGateway;
use App\Modules\Administracion\Application\DTOs\RespaldoData;

/**
 * El respaldo se genera a demanda ---desde la pantalla--- y de forma
 * programada, con la misma accion. Sin usuario lo pidio el planificador y
 * queda como programado; con usuario, como puntual.
 *
 * Si la generacion falla no se lanza nada: se devuelve la fila fallida,
 * con su error, que es lo que queda en el historial.
 */
final readonly class GenerarRespaldo
{
    public function __construct(
        private RespaldoGateway $respaldos,
    ) {}

    public function execute(?int $usuarioId = null): RespaldoData
    {
        return $this->respaldos->generar(
            $usuarioId === null
                ? RespaldoData::TIPO_PROGRAMADO
                : RespaldoData::TIPO_PUNTUAL,
            $usuarioId,
        );
    }
}
