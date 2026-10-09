<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * Un cambio de condicion en lote: sobre una lista de estudiantes o sobre
 * todo lo que dejan los filtros («Seleccionar los N»).
 */
final readonly class CambioHabilitacionData
{
    /**
     * @param  list<int>  $estudiantes
     */
    public function __construct(
        public bool $habilitado,
        public ?string $motivo,
        public array $estudiantes,
        public bool $todos,
        public FiltroHabilitacionData $filtros,
    ) {}
}
