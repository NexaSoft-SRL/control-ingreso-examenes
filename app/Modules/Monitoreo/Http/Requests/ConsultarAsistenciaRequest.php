<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Http\Requests;

use App\Modules\Monitoreo\Application\DTOs\ConsultaAsistenciaData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConsultarAsistenciaRequest extends FormRequest
{
    private const POR_PAGINA = 25;

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
            'lista' => [
                'sometimes',
                'string',
                Rule::in([ConsultaAsistenciaData::FALTAN, ConsultaAsistenciaData::INGRESARON]),
            ],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lista.in' => 'La lista debe ser «faltan» o «ingresaron».',
            'lista.string' => 'La lista debe ser «faltan» o «ingresaron».',
            'buscar.string' => 'La búsqueda debe ser un texto.',
            'buscar.max' => 'La búsqueda admite hasta 100 caracteres.',
            'pagina.integer' => 'La página debe ser un número entero.',
            'pagina.min' => 'La página debe ser 1 o mayor.',
            'por_pagina.integer' => 'El tamaño de página debe ser un número entero.',
            'por_pagina.min' => 'El tamaño de página debe ser 1 o mayor.',
            'por_pagina.max' => 'El tamaño de página admite hasta 100.',
        ];
    }

    public function toData(int $examenId, int $aulaId): ConsultaAsistenciaData
    {
        $lista = $this->validated('lista');
        $buscar = $this->validated('buscar');

        return new ConsultaAsistenciaData(
            examenId: $examenId,
            aulaId: $aulaId,
            lista: is_string($lista) ? $lista : ConsultaAsistenciaData::FALTAN,
            buscar: is_string($buscar) && trim($buscar) !== '' ? trim($buscar) : null,
            pagina: $this->has('pagina') ? $this->integer('pagina') : 1,
            porPagina: $this->has('por_pagina') ? $this->integer('por_pagina') : self::POR_PAGINA,
        );
    }
}
