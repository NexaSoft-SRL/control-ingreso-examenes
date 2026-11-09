<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListarGruposDeAsignaturaRequest extends FormRequest
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
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            ...$this->reglasDePagina(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->facultadEnMinusculas();
    }

    public function periodoId(): ?int
    {
        return $this->enteroOpcional('periodo');
    }

    public function facultad(): ?string
    {
        return $this->textoOpcional('facultad');
    }

    public function buscar(): ?string
    {
        return $this->textoOpcional('buscar');
    }
}
