<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use App\Modules\Academico\Application\DTOs\FiltroAsignaturasData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListarAsignaturasRequest extends FormRequest
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
            'nivel' => ['sometimes', 'nullable', 'string', 'max:30'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sin_lista' => ['sometimes', 'nullable', 'boolean'],
            ...$this->reglasDePagina(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->facultadEnMinusculas();
    }

    public function toData(): FiltroAsignaturasData
    {
        return new FiltroAsignaturasData(
            periodoId: $this->enteroOpcional('periodo'),
            facultad: $this->textoOpcional('facultad'),
            carreraId: $this->enteroOpcional('carrera'),
            nivel: $this->textoOpcional('nivel'),
            buscar: $this->textoOpcional('buscar'),
            sinLista: $this->boolean('sin_lista'),
        );
    }
}
