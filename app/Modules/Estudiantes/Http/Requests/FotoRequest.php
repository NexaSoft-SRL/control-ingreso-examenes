<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

final class FotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=240,min_height=240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Obligatoria',
            // Un archivo que supera el límite de PHP llega como fallo de subida.
            'foto.uploaded' => 'Máximo 8 MB',
            'foto.max' => 'Máximo 8 MB',
            'foto.dimensions' => 'Resolución insuficiente · mínimo 240 px',
            'foto.*' => 'Solo JPG, PNG o WebP',
        ];
    }

    public function imagen(): string
    {
        $archivo = $this->file('foto');

        if (! $archivo instanceof UploadedFile) {
            throw new LogicException('La validación exige la foto.');
        }

        return $archivo->getContent();
    }
}
