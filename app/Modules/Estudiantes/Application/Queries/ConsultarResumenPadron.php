<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Estudiantes\Application\Contracts\PadronGateway;

final readonly class ConsultarResumenPadron
{
    public function __construct(
        private PadronGateway $padron,
    ) {}

    /**
     * @return array{estudiantes: int, inscripciones: int, cargados_por_docentes: int, conflictos_pendientes: int}
     */
    public function execute(): array
    {
        return $this->padron->resumen();
    }
}
