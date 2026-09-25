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
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'ci' => [
                'required',
                'string',
                'max:30',
                Rule::unique('students', 'ci')->ignore($studentId),
            ],
            'correo' => [
                'required',
                'email',
                'max:150',
                Rule::unique('students', 'correo')->ignore($studentId),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
