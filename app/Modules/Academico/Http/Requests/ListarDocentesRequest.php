<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use App\Modules\Academico\Application\DTOs\FiltroDocentesData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListarDocentesRequest extends FormRequest
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
            'sin_cuenta' => ['sometimes', 'nullable', 'boolean'],
            'varias_facultades' => ['sometimes', 'nullable', 'boolean'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            ...$this->reglasDePagina(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->facultadEnMinusculas();
    }

    public function toData(): FiltroDocentesData
    {
        return new FiltroDocentesData(
            periodoId: $this->enteroOpcional('periodo'),
            facultad: $this->textoOpcional('facultad'),
            sinCuenta: $this->marcado('sin_cuenta'),
            variasFacultades: $this->marcado('varias_facultades'),
            buscar: $this->textoOpcional('buscar'),
        );
    }
}
