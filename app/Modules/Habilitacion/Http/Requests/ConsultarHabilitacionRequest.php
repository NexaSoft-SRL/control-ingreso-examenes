<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConsultarHabilitacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'identificador.required' => 'Escribe el código universitario o el documento de identidad.',
            'identificador.max' => 'El código o documento no puede superar los 30 caracteres.',
        ];
    }

    public function identificador(): string
    {
        /** @var array{identificador: string} $data */
        $data = $this->validated();

        return trim($data['identificador']);
    }
}
