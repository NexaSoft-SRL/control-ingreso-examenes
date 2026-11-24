<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Administracion\Application\Contracts\BuzonDeRebotes;
use App\Modules\Administracion\Application\DTOs\ReboteData;

/**
 * Buzon en memoria: devuelve los avisos dados y cuenta las lecturas.
 */
final class BuzonFalso implements BuzonDeRebotes
{
    /** @var list<ReboteData> */
    public array $avisos = [];

    public int $lecturas = 0;

    public function rebotes(int $dias): array
    {
        $this->lecturas++;

        return $this->avisos;
    }
}
