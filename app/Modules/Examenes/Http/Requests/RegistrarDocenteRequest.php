<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use App\Modules\Examenes\Application\DTOs\RegistrarDocenteData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegistrarDocenteRequest extends FormRequest
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
            'codigo_docente' => [
                'required',
                'string',
                'max:50',
                Rule::unique('docentes', 'codigo_docente'),
            ],
            'nombres' => [
                'required',
                'string',
                'max:100',
            ],
            'apellidos' => [
                'required',
                'string',
                'max:100',
            ],
            'correo' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('docentes', 'correo'),
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20',
            ],
            // La cuenta es opcional: un docente puede quedar registrado
            // antes de que se le cree el usuario del sistema.
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('usuarios', 'id'),
                Rule::unique('docentes', 'user_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_docente.unique' => 'Ya existe un docente con ese código.',
            'correo.unique' => 'Ya existe un docente con ese correo.',
            'user_id.unique' => 'Esa cuenta ya está vinculada a otro docente.',
        ];
    }

    public function toData(): RegistrarDocenteData
    {
        /**
         * @var array{
         *     codigo_docente: string,
         *     nombres: string,
         *     apellidos: string,
         *     correo?: string|null,
         *     telefono?: string|null,
         *     user_id?: int|null
         * } $validated
         */
        $validated = $this->validated();

        return new RegistrarDocenteData(
            codigoDocente: $validated['codigo_docente'],
            nombres: $validated['nombres'],
            apellidos: $validated['apellidos'],
            correo: $validated['correo'] ?? null,
            telefono: $validated['telefono'] ?? null,
            cuentaId: $validated['user_id'] ?? null,
        );
    }
}
