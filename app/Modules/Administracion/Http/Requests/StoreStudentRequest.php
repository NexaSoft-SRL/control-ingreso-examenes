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
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'ci' => ['required', 'string', 'max:30', 'unique:students,ci'],
            'correo' => ['required', 'email', 'max:150', 'unique:students,correo'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
