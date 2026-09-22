<?php

namespace App\Modules\Administracion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre'   => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'ci'       => 'required|string|max:20|unique:students,ci',
            'correo'   => 'required|email|unique:students,correo',
            'activo'   => 'boolean',
        ];
    }
}

