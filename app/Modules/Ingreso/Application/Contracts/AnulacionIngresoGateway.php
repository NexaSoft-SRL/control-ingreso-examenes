<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\IngresoAnulableData;

/**
 * La anulación de un ingreso: una corrección que se asienta aparte y deja el
 * ingreso original como estaba (requisito 29).
 */
interface AnulacionIngresoGateway
{
    /**
     * El ingreso del historial, también si ya está anulado.
     */
    public function ingreso(int $ingresoId): ?IngresoAnulableData;

    /**
     * Asienta la anulación y la deja en la bitácora. Falso si otro puesto la
     * asentó antes: una sola por ingreso.
     */
    public function anular(int $ingresoId, string $motivo, int $usuarioId): bool;
}
