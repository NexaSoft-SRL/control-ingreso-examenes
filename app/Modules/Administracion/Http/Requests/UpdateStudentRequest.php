<?php

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentId = (int) $this->route('student');

        return [
            'carrera_id' => ['sometimes', 'integer', 'exists:carreras,id'],
            'codigo_sis' => ['sometimes', 'string', 'max:30', Rule::unique('estudiantes', 'codigo_sis')->ignore($studentId)],
            'ci' => ['sometimes', 'string', 'max:30', Rule::unique('estudiantes', 'ci')->ignore($studentId)],
            'nombres' => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:100'],
            'correo' => ['nullable', 'email', 'max:150', Rule::unique('estudiantes', 'correo')->ignore($studentId)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'estado' => ['sometimes', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
