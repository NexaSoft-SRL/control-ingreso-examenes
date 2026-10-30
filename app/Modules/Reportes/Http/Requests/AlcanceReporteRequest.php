<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use App\Modules\Reportes\Application\DTOs\AlcanceReporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los filtros de los reportes de la universidad: periodo, facultad, texto
 * y fechas, o un examen elegido.
 */
final class AlcanceReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $facultad = $this->query('facultad');

        // Se admite la sigla (`FCyT`) ademas de la clave (`fcyt`).
        if (is_string($facultad)) {
            $this->merge(['facultad' => mb_strtolower(trim($facultad))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'periodo' => ['nullable', 'integer', Rule::exists('periodos', 'id')],
            'facultad' => ['nullable', 'string', 'max:10', Rule::exists('facultades', 'clave')],
            'buscar' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'examen' => ['nullable', 'integer', Rule::exists('examenes', 'id')],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
            'formato' => ['nullable', Rule::in(['pdf', 'xlsx'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periodo.integer' => 'El período no es válido.',
            'periodo.exists' => 'El período no existe.',
            'facultad.string' => 'La facultad no es válida.',
            'facultad.max' => 'La facultad no es válida.',
            'facultad.exists' => 'La facultad no existe.',
            'buscar.string' => 'El texto de búsqueda no es válido.',
            'buscar.max' => 'El texto de búsqueda no puede pasar de 100 caracteres.',
            'desde.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'examen.integer' => 'El examen no es válido.',
            'examen.exists' => 'El examen no existe.',
            'pagina.integer' => 'La página debe ser un número.',
            'pagina.min' => 'La página debe ser 1 o mayor.',
            'por_pagina.integer' => 'El tamaño de página debe ser un número.',
            'por_pagina.min' => 'El tamaño de página debe ser 1 o mayor.',
            'por_pagina.max' => 'El tamaño de página no puede pasar de 100.',
            'formato.in' => 'Los reportes se exportan a PDF (pdf) o a hoja de cálculo (xlsx).',
        ];
    }

    public function toAlcance(): AlcanceReporte
    {
        return new AlcanceReporte(
            periodoId: $this->entero('periodo'),
            facultad: $this->cadena('facultad'),
            buscar: $this->cadena('buscar'),
            desde: $this->cadena('desde'),
            hasta: $this->cadena('hasta'),
            examenId: $this->entero('examen'),
        );
    }

    public function pagina(): int
    {
        return $this->entero('pagina') ?? 1;
    }

    public function porPagina(): int
    {
        return $this->entero('por_pagina') ?? 10;
    }

    public function formato(): string
    {
        return $this->validated('formato') === 'xlsx' ? 'xlsx' : 'pdf';
    }

    // Los filtros llegan en la dirección: siempre como texto.
    private function entero(string $campo): ?int
    {
        $valor = $this->validated($campo);

        return is_numeric($valor) ? (int) $valor : null;
    }

    private function cadena(string $campo): ?string
    {
        $valor = $this->validated($campo);

        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }
}
