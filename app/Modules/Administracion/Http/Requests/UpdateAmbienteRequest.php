<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAmbienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ambienteId = (int) $this->route('ambiente');

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('ambientes', 'nombre')->ignore($ambienteId),
            ],
            'ubicacion' => ['nullable', 'string', 'max:200'],
            'capacidad' => ['required', 'integer', 'min:1'],
            'estado' => ['sometimes', 'string', Rule::in(['DISPONIBLE', 'MANTENIMIENTO', 'OCUPADO'])],
        ];
    }
}
