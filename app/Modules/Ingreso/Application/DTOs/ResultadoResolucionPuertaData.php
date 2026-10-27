<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;

final readonly class ResultadoResolucionPuertaData
{
    public function __construct(
        public ?int $examenId,
        public ?VeredictoIngreso $veredicto,
        public ?string $codigoQrResuelto = null,
        public ?CandidatoIngresoData $candidato = null,
        // Sin examen resuelto: por que (no inscrito, sin registro, QR).
        public ?CausaIntento $causa = null,
        // El estudiante del padron cuando no esta inscrito en el aula.
        public ?EstudianteData $estudiante = null,
    ) {}

    public function puedeAdmitir(): bool
    {
        return $this->examenId !== null
            && $this->veredicto === VeredictoIngreso::Admitido;
    }
}
