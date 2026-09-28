<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
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
            'codigo_universitario' => [
                'nullable', 'string', 'max:20', 'unique:students,codigo_universitario',
            ],
            'carrera' => ['nullable', 'string', 'max:120'],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'ci' => ['required', 'string', 'max:30', 'unique:students,ci'],
            'correo' => ['nullable', 'email', 'max:150', 'unique:students,correo'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
