<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class ReportarIncidenciaRequest extends FormRequest
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
        return [
            'aula_id' => ['required', 'integer', 'min:1'],
            // La expulsion va por su propia ruta, que exige el ingreso.
            'tipo' => ['required', 'string', Rule::in(TipoIncidencia::reportables())],
            'estudiante' => ['nullable', 'string', 'max:150'],
            'detalle' => ['required', 'string', 'min:5', 'max:2000'],
            'evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aula_id.*' => 'Indique el aula.',
            'tipo.*' => 'Elija el tipo de incidencia.',
            'estudiante.*' => 'Máximo 150 caracteres',
            'detalle.required' => 'Mínimo 5 caracteres',
            'detalle.string' => 'Mínimo 5 caracteres',
            'detalle.min' => 'Mínimo 5 caracteres',
            'detalle.max' => 'Máximo 2000 caracteres',
            'evidencia.uploaded' => 'Máximo 8 MB',
            'evidencia.max' => 'Máximo 8 MB',
            'evidencia.*' => 'Solo JPG, PNG o WebP',
        ];
    }

    public function aulaId(): int
    {
        return $this->integer('aula_id');
    }

    public function tipo(): TipoIncidencia
    {
        return TipoIncidencia::from($this->string('tipo')->toString());
    }

    public function estudiante(): ?string
    {
        $estudiante = $this->validated('estudiante');

        return is_string($estudiante) ? $estudiante : null;
    }

    public function detalle(): string
    {
        return trim($this->string('detalle')->toString());
    }

    public function evidencia(): ?string
    {
        $archivo = $this->file('evidencia');

        return $archivo instanceof UploadedFile ? $archivo->getContent() : null;
    }
}
