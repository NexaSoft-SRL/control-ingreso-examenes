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
            /*
             * TODO (HU-08): Validar que la nueva capacidad no sea menor
             * que la cantidad de estudiantes ya asignados a este ambiente
             * en examenes activos. Esta validacion requiere la tabla
             * examen_ambiente y habilitaciones_examen que se implementan
             * en HU-08 (Registro de examenes).
             *
             * Coordinacion pendiente con el responsable de HU-08.
             */
            'capacidad' => ['required', 'integer', 'min:1'],
            'estado' => ['sometimes', 'string', Rule::in(['DISPONIBLE', 'MANTENIMIENTO', 'OCUPADO'])],
        ];
    }
}
