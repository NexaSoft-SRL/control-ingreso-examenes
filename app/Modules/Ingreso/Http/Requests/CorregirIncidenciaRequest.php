<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class CorregirIncidenciaRequest extends FormRequest
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
            'tipo' => ['required', 'string', Rule::in(TipoIncidencia::valores())],
            'detalle' => ['required', 'string', 'min:5', 'max:2000'],
            'estudiante' => ['sometimes', 'nullable', 'string', 'max:150'],
            'evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.*' => 'Tipo no válido',
            'detalle.required' => 'Mínimo 5 caracteres',
            'detalle.string' => 'Mínimo 5 caracteres',
            'detalle.min' => 'Mínimo 5 caracteres',
            'detalle.max' => 'Máximo 2000 caracteres',
            'estudiante.*' => 'Máximo 150 caracteres',
            'evidencia.uploaded' => 'Máximo 8 MB',
            'evidencia.max' => 'Máximo 8 MB',
            'evidencia.*' => 'Solo JPG, PNG o WebP',
        ];
    }

    public function tipo(): TipoIncidencia
    {
        return TipoIncidencia::from($this->string('tipo')->toString());
    }

    public function detalle(): string
    {
        return trim($this->string('detalle')->toString());
    }

    /**
     * Si la correccion trae el estudiante (vacio lo quita).
     */
    public function cambiaEstudiante(): bool
    {
        return $this->has('estudiante');
    }

    public function estudiante(): ?string
    {
        $texto = trim($this->string('estudiante')->toString());

        return $texto === '' ? null : $texto;
    }

    public function evidencia(): ?string
    {
        $archivo = $this->file('evidencia');

        return $archivo instanceof UploadedFile ? $archivo->getContent() : null;
    }
}
