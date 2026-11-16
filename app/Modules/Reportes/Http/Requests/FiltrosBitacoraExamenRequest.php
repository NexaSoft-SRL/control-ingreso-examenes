<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FiltrosBitacoraExamenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', Rule::in(['ingreso', 'rechazo', 'incidencia', 'acceso', 'examen', 'ausente'])],
            'gravedad' => ['nullable', Rule::in(['LEVE', 'GRAVE', 'MUY_GRAVE'])],
            'aula' => ['nullable', 'string', 'max:100'],
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
            'buscar.max' => 'La búsqueda no puede pasar de 100 caracteres.',
            'categoria.in' => 'El tipo de suceso no es válido.',
            'gravedad.in' => 'La gravedad no es válida.',
            'aula.max' => 'El aula no es válida.',
            'pagina.integer' => 'La página no es válida.',
            'pagina.min' => 'La página no es válida.',
            'por_pagina.integer' => 'El tamaño de página no es válido.',
            'por_pagina.min' => 'El tamaño de página no es válido.',
            'por_pagina.max' => 'No se pueden pedir más de 100 sucesos por página.',
        ];
    }

    /**
     * @return array{buscar: string|null, categoria: string|null, gravedad: string|null, aula: string|null, pagina: int, por_pagina: int}
     */
    public function filtros(): array
    {
        return [
            'buscar' => $this->texto('buscar'),
            'categoria' => $this->texto('categoria'),
            'gravedad' => $this->texto('gravedad'),
            'aula' => $this->texto('aula'),
            'pagina' => max(1, $this->integer('pagina', 1)),
            'por_pagina' => max(1, $this->integer('por_pagina', 100)),
        ];
    }

    private function texto(string $campo): ?string
    {
        $valor = $this->validated($campo);

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
