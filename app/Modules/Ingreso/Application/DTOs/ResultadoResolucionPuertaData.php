<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;

final readonly class ResultadoResolucionPuertaData
{
    public function __construct(
        public ?int $examenId,
        public ?VeredictoIngreso $veredicto,
        public bool $ambiguo = false,
        public ?string $codigoQrResuelto = null,
        public ?CandidatoIngresoData $candidato = null,
    ) {}

    public function puedeAdmitir(): bool
    {
        return $this->examenId !== null
            && $this->veredicto === VeredictoIngreso::Admitido
            && ! $this->ambiguo;
    }
}
