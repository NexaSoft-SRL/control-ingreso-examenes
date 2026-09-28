<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
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
        $studentId = (int) $this->route('student');

        return [
            'codigo_universitario' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('students', 'codigo_universitario')->ignore($studentId),
            ],
            'carrera' => ['nullable', 'string', 'max:120'],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'ci' => [
                'required',
                'string',
                'max:30',
                Rule::unique('students', 'ci')->ignore($studentId),
            ],
            'correo' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('students', 'correo')->ignore($studentId),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
