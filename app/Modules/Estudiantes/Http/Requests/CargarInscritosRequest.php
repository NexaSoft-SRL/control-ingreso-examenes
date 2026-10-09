<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * El archivo de una carga (rutas 36 y 41). La de facultad pide ademas la
 * clave de la facultad.
 */
final class CargarInscritosRequest extends FormRequest
{
    /**
     * El servidor de destino admite envios de hasta 8 MB en total
     * (`post_max_size`): el archivo se limita a 7 MB para dejar margen al
     * resto del formulario.
     */
    private const MAXIMO_KB = 7168;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $reglas = [
            'archivo' => ['required', 'file', 'extensions:csv,xlsx', 'max:'.self::MAXIMO_KB],
        ];

        if ($this->routeIs('estudiantes.cargas.store')) {
            $reglas['facultad'] = ['required', 'string', 'max:10', 'exists:facultades,clave'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El archivo no se pudo recibir.',
            'archivo.uploaded' => 'El archivo no se pudo recibir.',
            'archivo.extensions' => 'El archivo debe ser .csv o .xlsx.',
            'archivo.max' => 'El archivo no puede superar los 7 MB.',
            'facultad.required' => 'La facultad es obligatoria.',
            'facultad.string' => 'La facultad no es válida.',
            'facultad.max' => 'La facultad no es válida.',
            'facultad.exists' => 'La facultad no existe.',
        ];
    }

    public function archivo(): ?UploadedFile
    {
        $archivo = $this->file('archivo');

        return $archivo instanceof UploadedFile ? $archivo : null;
    }

    public function facultad(): string
    {
        return $this->string('facultad')->trim()->toString();
    }
}
