<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\ReboteData;

/**
 * El buzon del remitente, donde el servidor de correo deja los avisos de
 * los correos que no se pudieron entregar.
 */
interface BuzonDeRebotes
{
    /**
     * Los rebotes permanentes de los ultimos dias, leidos o no por una
     * persona: el buzon no se modifica. Volver a leer un aviso no hace dano,
     * porque sus envios ya quedaron como fallidos. Un fallo al leer el buzon
     * no es un error de la aplicacion: devuelve una lista vacia.
     *
     * @return list<ReboteData>
     */
    public function rebotes(int $dias): array;
}
