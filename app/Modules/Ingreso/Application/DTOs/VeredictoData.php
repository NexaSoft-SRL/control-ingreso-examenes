<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

use App\Modules\Ingreso\Domain\Enums\Veredicto;

/**
 * Lo que responde la puerta al verificar. `extras` lleva lo propio de
 * cada veredicto: `ingreso`, `aula_correcta` o `examen_correspondiente`.
 */
final readonly class VeredictoData
{
    /**
     * @param  array<string, mixed>  $extras
     */
    public function __construct(
        public Veredicto $veredicto,
        public ?EstudianteData $estudiante = null,
        public ?ExamenData $examen = null,
        public ?string $dato = null,
        public ?string $pie = null,
        public array $extras = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function comoArreglo(): array
    {
        return array_merge([
            'veredicto' => $this->veredicto->value,
            'estudiante' => $this->estudiante?->comoArreglo(),
            'examen' => $this->examen === null ? null : [
                'id' => $this->examen->id,
                'asignatura' => $this->examen->asignatura,
                'tipo' => $this->examen->tipo,
            ],
            'dato' => $this->dato,
            'pie' => $this->pie,
        ], $this->extras);
    }
}
