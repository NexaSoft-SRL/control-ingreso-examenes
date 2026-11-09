<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo que piden las pantallas del docente: el listado de sus examenes, la
 * descarga de un examen (ahi `examen` es obligatorio) y
 * la lista de un grupo. Que el examen exista y sea suyo no es validacion:
 * responde 404 o 403.
 */
final class ReporteExamenRequest extends FormRequest
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
        $descarga = $this->routeIs(
            'reportes.asistencia.exportar',
            'reportes.ausentes.exportar',
            'reportes.incidencias.exportar',
        );

        return [
            'examen' => [$descarga ? 'required' : 'nullable', 'integer', 'min:1'],
            'formato' => [
                'nullable',
                Rule::in($this->routeIs('reportes.grupo.estudiantes') ? ['xlsx'] : ['pdf', 'xlsx']),
            ],
            'periodo' => ['nullable', 'integer', Rule::exists('periodos', 'id')],
            'asignatura' => ['nullable', 'integer', 'min:1'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'examen.required' => 'Elija el examen del reporte.',
            'examen.integer' => 'El examen no es válido.',
            'examen.min' => 'El examen no es válido.',
            'formato.in' => $this->routeIs('reportes.grupo.estudiantes')
                ? 'La lista del grupo se descarga como hoja de cálculo (xlsx).'
                : 'Los reportes se exportan a PDF (pdf) o a hoja de cálculo (xlsx).',
            'periodo.integer' => 'El período no es válido.',
            'periodo.exists' => 'El período no existe.',
            'asignatura.integer' => 'La asignatura no es válida.',
            'asignatura.min' => 'La asignatura no es válida.',
            'pagina.integer' => 'La página debe ser un número.',
            'pagina.min' => 'La página debe ser 1 o mayor.',
            'por_pagina.integer' => 'El tamaño de página debe ser un número.',
            'por_pagina.min' => 'El tamaño de página debe ser 1 o mayor.',
            'por_pagina.max' => 'El tamaño de página no puede pasar de 100.',
        ];
    }

    public function examenId(): int
    {
        return $this->entero('examen') ?? 0;
    }

    public function periodoId(): ?int
    {
        return $this->entero('periodo');
    }

    public function asignaturaId(): ?int
    {
        return $this->entero('asignatura');
    }

    public function pagina(): int
    {
        return $this->entero('pagina') ?? 1;
    }

    public function porPagina(): int
    {
        return $this->entero('por_pagina') ?? 8;
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
}
