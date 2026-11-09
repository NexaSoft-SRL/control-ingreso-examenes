<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Domain\Rules\DatosDeInscrito;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El estudiante que el docente agrega suelto: valida como una fila del
 * archivo de carga.
 */
final class AgregarInscritoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'regex:'.DatosDeInscrito::PATRON_CODIGO],
            'documento' => ['required', 'string', 'max:'.DatosDeInscrito::MAXIMO_DOCUMENTO, 'regex:'.DatosDeInscrito::PATRON_DOCUMENTO],
            'nombres' => ['required', 'string', 'max:'.DatosDeInscrito::MAXIMO_NOMBRE, 'regex:'.DatosDeInscrito::PATRON_NOMBRE],
            'apellidos' => ['required', 'string', 'max:'.DatosDeInscrito::MAXIMO_NOMBRE, 'regex:'.DatosDeInscrito::PATRON_NOMBRE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'El código SIS es obligatorio.',
            'codigo.string' => 'El código SIS no es válido.',
            'codigo.regex' => 'El código SIS debe tener entre 5 y 10 dígitos.',
            'documento.required' => 'El documento es obligatorio.',
            'documento.string' => 'El documento no es válido.',
            'documento.max' => 'El documento no puede superar los '.DatosDeInscrito::MAXIMO_DOCUMENTO.' caracteres.',
            'documento.regex' => 'De 4 a 10 dígitos y su complemento («5003748-1A»).',
            'nombres.regex' => 'Solo letras, sin signos seguidos.',
            'apellidos.regex' => 'Solo letras, sin signos seguidos.',
            'nombres.required' => 'Los nombres son obligatorios.',
            'nombres.string' => 'Los nombres no son válidos.',
            'nombres.max' => 'Los nombres no pueden superar los '.DatosDeInscrito::MAXIMO_NOMBRE.' caracteres.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'apellidos.string' => 'Los apellidos no son válidos.',
            'apellidos.max' => 'Los apellidos no pueden superar los '.DatosDeInscrito::MAXIMO_NOMBRE.' caracteres.',
        ];
    }

    public function codigo(): string
    {
        return $this->string('codigo')->trim()->toString();
    }

    public function documento(): string
    {
        return $this->string('documento')->trim()->toString();
    }

    public function nombres(): string
    {
        return $this->string('nombres')->squish()->toString();
    }

    public function apellidos(): string
    {
        return $this->string('apellidos')->squish()->toString();
    }
}
