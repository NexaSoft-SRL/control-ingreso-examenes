<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Domain\Enums\TipoExcepcion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegistrarExcepcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $admite = TipoExcepcion::CambioAula->value;

        return [
            'tipo' => ['required', 'string', Rule::in(TipoExcepcion::valores())],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'estudiante_id' => ["required_if:tipo,{$admite}", 'nullable', 'integer', 'exists:estudiantes,id'],
            'examen_id' => ["required_if:tipo,{$admite}", 'nullable', 'integer', 'exists:examenes,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.*' => 'Elija el tipo de situación.',
            'motivo.required' => 'Mínimo 5 caracteres',
            'motivo.string' => 'Mínimo 5 caracteres',
            'motivo.min' => 'Mínimo 5 caracteres',
            'motivo.max' => 'Máximo 500 caracteres',
            'estudiante_id.required_if' => 'Indique el estudiante.',
            'estudiante_id.*' => 'El estudiante no existe.',
            'examen_id.required_if' => 'Indique el examen.',
            'examen_id.*' => 'El examen no existe.',
        ];
    }

    public function tipo(): TipoExcepcion
    {
        return TipoExcepcion::from($this->string('tipo')->toString());
    }

    public function motivo(): string
    {
        return trim($this->string('motivo')->toString());
    }

    public function estudianteId(): ?int
    {
        return $this->filled('estudiante_id') ? $this->integer('estudiante_id') : null;
    }

    public function examenId(): ?int
    {
        return $this->filled('examen_id') ? $this->integer('examen_id') : null;
    }
}
