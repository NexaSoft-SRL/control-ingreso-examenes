<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Administracion\Application\Contracts\BuzonDeRebotes;
use App\Modules\Administracion\Application\DTOs\ReboteData;

/**
 * Buzon en memoria: devuelve los avisos dados y anota lo que se archiva.
 */
final class BuzonFalso implements BuzonDeRebotes
{
    /** @var list<ReboteData> */
    public array $avisos = [];

    /** @var list<list<string>> */
    public array $archivados = [];

    public int $lecturas = 0;

    public function rebotes(): array
    {
        $this->lecturas++;

        return $this->avisos;
    }

    public function archivar(array $mensajeIds): void
    {
        $this->archivados[] = $mensajeIds;
    }
}
