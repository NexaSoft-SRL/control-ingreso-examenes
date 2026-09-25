<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAmbienteRequest extends FormRequest
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
        return [
            'nombre' => ['required', 'string', 'max:100', 'unique:ambientes,nombre'],
            'ubicacion' => ['nullable', 'string', 'max:200'],
            'capacidad' => ['required', 'integer', 'min:1'],
            'estado' => ['sometimes', 'string', Rule::in(['DISPONIBLE', 'MANTENIMIENTO', 'OCUPADO'])],
        ];
    }
}
