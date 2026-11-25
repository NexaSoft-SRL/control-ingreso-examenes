<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FiltroUbicacionRequest extends FormRequest
{
    use ValidaEnEspanol;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'facultad' => ['sometimes', 'nullable', 'string', Rule::exists('facultades', 'clave')],
            'ubicadas' => ['sometimes', 'nullable', 'boolean'],
            // Solo las carreras con oferta en el período (por omisión, los vigentes).
            'con_oferta' => ['sometimes', 'nullable', 'boolean'],
            'periodo' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->facultadEnMinusculas();
    }

    public function facultad(): ?string
    {
        return $this->textoOpcional('facultad');
    }

    public function conOferta(): bool
    {
        return $this->marcado('con_oferta');
    }

    public function periodoId(): ?int
    {
        return $this->enteroOpcional('periodo');
    }

    public function soloUbicadas(): bool
    {
        return $this->marcado('ubicadas');
    }
}
