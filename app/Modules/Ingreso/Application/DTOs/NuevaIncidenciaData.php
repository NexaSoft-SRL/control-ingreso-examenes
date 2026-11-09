<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;

final readonly class NuevaIncidenciaData
{
    /**
     * @param  list<string>  $evidencias  rutas de las fotos ya guardadas, en orden
     */
    public function __construct(
        public TipoIncidencia $tipo,
        public OrigenIncidencia $origen,
        public string $detalle,
        public OperadorData $operador,
        public ?int $examenId = null,
        public ?int $aulaId = null,
        public ?int $estudianteId = null,
        public ?string $estudianteTexto = null,
        public ?int $ingresoId = null,
        public ?CausaIntento $causa = null,
        public ?int $examenCorrespondienteId = null,
        public array $evidencias = [],
        // Sin gravedad, la del tipo. `tipoTexto`: el nombre de una no prevista.
        public ?Gravedad $gravedad = null,
        public ?string $tipoTexto = null,
    ) {}
}
