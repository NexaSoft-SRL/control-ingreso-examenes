<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * La condicion registrada de un estudiante frente a un examen. Es lo que
 * lee la puerta (modulo Ingreso): si no hay condicion registrada, el
 * estudiante esta «sin revisar» y quien consulta recibe `null`.
 */
final readonly class HabilitacionData
{
    public function __construct(
        public int $examenId,
        public int $estudianteId,
        public bool $habilitado,
        /** El aula que le toca; `null` = sin repartir (o no habilitado). */
        public ?int $aulaId,
        public ?string $aulaNombre,
        /** Solo en los no habilitados. */
        public ?string $motivo,
        public ?int $registradaPorId,
        public ?string $registradaPor,
        /** Instante ISO 8601 con zona `America/La_Paz`. */
        public ?string $registradaEn,
    ) {}
}
