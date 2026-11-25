<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use App\Modules\Estudiantes\Domain\Rules\DatosDeInscrito;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Los datos corregidos de un estudiante: valida como una fila del archivo
 * de carga. El codigo SIS es opcional: solo la administracion lo corrige.
 */
final class EditarEstudianteRequest extends FormRequest
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
            'codigo' => ['sometimes', 'string', 'regex:'.DatosDeInscrito::PATRON_CODIGO],
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
            'codigo.regex' => 'El código SIS debe tener entre 5 y 10 dígitos.',
            'codigo.string' => 'El código SIS no es válido.',
            'documento.required' => 'El documento es obligatorio.',
            'documento.string' => 'El documento no es válido.',
            'documento.max' => DatosDeInscrito::MENSAJE_DOCUMENTO.'.',
            'documento.regex' => DatosDeInscrito::MENSAJE_DOCUMENTO.'.',
            'nombres.regex' => 'Escriba un nombre real: solo letras, mínimo 3.',
            'apellidos.regex' => 'Escriba un nombre real: solo letras, mínimo 3.',
            'nombres.required' => 'Los nombres son obligatorios.',
            'nombres.string' => 'Los nombres no son válidos.',
            'nombres.max' => 'Los nombres no pueden superar los '.DatosDeInscrito::MAXIMO_NOMBRE.' caracteres.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'apellidos.string' => 'Los apellidos no son válidos.',
            'apellidos.max' => 'Los apellidos no pueden superar los '.DatosDeInscrito::MAXIMO_NOMBRE.' caracteres.',
        ];
    }

    public function documento(): string
    {
        return $this->string('documento')->trim()->toString();
    }

    public function codigo(): ?string
    {
        return $this->has('codigo') ? $this->string('codigo')->trim()->toString() : null;
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
