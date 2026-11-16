<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * La foto de quien intenta entrar sin corresponderle.
 */
final class FotoDeIntentoRequest extends FormRequest
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
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Tome la foto',
            'foto.uploaded' => 'Máximo 8 MB',
            'foto.max' => 'Máximo 8 MB',
            'foto.*' => 'Solo JPG, PNG o WebP',
        ];
    }

    public function contenido(): string
    {
        $archivo = $this->file('foto');

        return $archivo instanceof UploadedFile ? (string) file_get_contents($archivo->getRealPath()) : '';
    }
}
