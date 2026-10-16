<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FiltroOfertaRequest extends FormRequest
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
            'periodo' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'facultad' => ['sometimes', 'nullable', 'string', Rule::exists('facultades', 'clave')],
            'carrera' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'nivel' => ['sometimes', 'nullable', 'string', 'max:50'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->facultadEnMinusculas();
    }

    /**
     * @return array{periodo_ids: list<int>, facultad: string|null, carrera_id: int|null, nivel: string|null, buscar: string|null}
     */
    public function filtros(): array
    {
        $periodo = $this->enteroOpcional('periodo');

        return [
            'periodo_ids' => $periodo === null ? [] : [$periodo],
            'facultad' => $this->facultad(),
            'carrera_id' => $this->enteroOpcional('carrera'),
            'nivel' => $this->textoOpcional('nivel'),
            'buscar' => $this->buscar(),
        ];
    }

    public function facultad(): ?string
    {
        return $this->textoOpcional('facultad');
    }

    public function buscar(): ?string
    {
        $texto = $this->textoOpcional('buscar');

        return $texto === null ? null : trim($texto);
    }
}
