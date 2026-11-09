<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class ReportarIncidenciaRequest extends FormRequest
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
            'aula_id' => ['required', 'integer', 'min:1'],
            // La expulsion va por su propia ruta, que exige el ingreso.
            'tipo' => ['required', 'string', Rule::in(TipoIncidencia::reportables())],
            'estudiante' => ['nullable', 'string', 'max:150'],
            'detalle' => ['required', 'string', 'min:5', 'max:2000'],
            // La gravedad la elige quien registra (sin ella, la sugerida) y una
            // no prevista lleva su nombre.
            'gravedad' => ['nullable', 'string', Rule::in(Gravedad::valores())],
            'tipo_texto' => ['nullable', 'string', 'max:80'],
            'guardar_comun' => ['nullable', 'boolean'],
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
            'aula_id.*' => 'Indique el aula.',
            'tipo.*' => 'Elija el tipo de incidencia.',
            'estudiante.*' => 'Máximo 150 caracteres',
            'detalle.required' => 'Mínimo 5 caracteres',
            'detalle.string' => 'Mínimo 5 caracteres',
            'detalle.min' => 'Mínimo 5 caracteres',
            'detalle.max' => 'Máximo 2000 caracteres',
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

    public function aulaId(): int
    {
        return $this->integer('aula_id');
    }

    public function tipo(): TipoIncidencia
    {
        return TipoIncidencia::from($this->string('tipo')->toString());
    }

    public function estudiante(): ?string
    {
        $estudiante = $this->validated('estudiante');

        return is_string($estudiante) ? $estudiante : null;
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

    public function guardarComun(): bool
    {
        return $this->boolean('guardar_comun');
    }

    public function detalle(): string
    {
        return trim($this->string('detalle')->toString());
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
