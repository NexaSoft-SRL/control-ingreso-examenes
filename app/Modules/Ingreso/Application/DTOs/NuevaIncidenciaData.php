<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;

final readonly class NuevaIncidenciaData
{
    public function __construct(
        public TipoIncidencia $tipo,
        public OrigenIncidencia $origen,
        public string $detalle,
        public OperadorPuertaData $operador,
        public ?int $examenId = null,
        public ?int $aulaId = null,
        public ?int $estudianteId = null,
        public ?string $estudianteTexto = null,
        public ?int $ingresoId = null,
        public ?CausaIntento $causa = null,
        public ?string $evidencia = null,
    ) {}
}
