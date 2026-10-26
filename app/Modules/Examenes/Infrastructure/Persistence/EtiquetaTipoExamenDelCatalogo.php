<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\EtiquetaTipoExamen;
use App\Modules\Examenes\Domain\Enums\TipoExamen;

/**
 * Un codigo que no esta en el catalogo se devuelve tal cual.
 */
final class EtiquetaTipoExamenDelCatalogo implements EtiquetaTipoExamen
{
    public function de(string $tipo): string
    {
        return TipoExamen::tryFrom($tipo)?->etiqueta() ?? $tipo;
    }
}
