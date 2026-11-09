<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class CorregirIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', Rule::in(TipoIncidencia::valores())],
            'detalle' => ['required', 'string', 'min:5', 'max:2000'],
            // La gravedad la elige quien registra (sin ella, la sugerida) y una
            // no prevista lleva su nombre.
            'gravedad' => ['nullable', 'string', Rule::in(Gravedad::valores())],
            'tipo_texto' => ['nullable', 'string', 'max:80'],
            'estudiante' => ['sometimes', 'nullable', 'string', 'max:150'],
            // Hasta tres fotos; `evidencia`, una suelta, como antes.
            'evidencias' => ['nullable', 'array', 'max:3'],
            'evidencias.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.*' => 'Tipo no válido',
            'detalle.required' => 'Mínimo 5 caracteres',
            'detalle.string' => 'Mínimo 5 caracteres',
            'detalle.min' => 'Mínimo 5 caracteres',
            'detalle.max' => 'Máximo 2000 caracteres',
            'estudiante.*' => 'Máximo 150 caracteres',
            'gravedad.*' => 'Elija leve, grave o muy grave.',
            'tipo_texto.*' => 'Máximo 80 caracteres',
            'evidencias.max' => 'Máximo 3 fotos',
            'evidencias.array' => 'Solo JPG, PNG o WebP',
            'evidencias.*.uploaded' => 'Máximo 8 MB por foto',
            'evidencias.*.max' => 'Máximo 8 MB por foto',
            'evidencias.*.*' => 'Solo JPG, PNG o WebP',
            'evidencia.uploaded' => 'Máximo 8 MB',
            'evidencia.max' => 'Máximo 8 MB',
            'evidencia.*' => 'Solo JPG, PNG o WebP',
        ];
    }

    public function tipo(): TipoIncidencia
    {
        return TipoIncidencia::from($this->string('tipo')->toString());
    }

    public function gravedad(): ?Gravedad
    {
        return Gravedad::tryFrom($this->string('gravedad')->toString());
    }

    /**
     * El nombre de una incidencia no prevista; vacio, null.
     */
    public function tipoTexto(): ?string
    {
        $texto = trim($this->string('tipo_texto')->toString());

        return $texto === '' ? null : $texto;
    }

    public function detalle(): string
    {
        return trim($this->string('detalle')->toString());
    }

    /**
     * Si la correccion trae el estudiante (vacio lo quita).
     */
    public function cambiaEstudiante(): bool
    {
        return $this->has('estudiante');
    }

    public function estudiante(): ?string
    {
        $texto = trim($this->string('estudiante')->toString());

        return $texto === '' ? null : $texto;
    }

    /**
     * El contenido de cada foto, en el orden en que vinieron.
     *
     * @return list<string>
     */
    public function evidencias(): array
    {
        $archivos = $this->file('evidencias');
        $archivos = is_array($archivos) ? $archivos : [];
        $suelta = $this->file('evidencia');

        if ($suelta instanceof UploadedFile) {
            array_unshift($archivos, $suelta);
        }

        $contenidos = [];

        foreach ($archivos as $archivo) {
            $contenidos[] = $archivo->getContent();
        }

        return $contenidos;
    }
}
