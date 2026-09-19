<?php

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carrera_id' => ['required', 'integer', 'exists:carreras,id'],
            'codigo_sis' => ['required', 'string', 'max:30', Rule::unique('estudiantes', 'codigo_sis')],
            'ci' => ['required', 'string', 'max:30', Rule::unique('estudiantes', 'ci')],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo' => ['nullable', 'email', 'max:150', Rule::unique('estudiantes', 'correo')],
            'telefono' => ['nullable', 'string', 'max:30'],
            'estado' => ['sometimes', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
