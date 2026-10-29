<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Application\DTOs\FiltroRespaldosData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListarRespaldosRequest extends FormRequest
{
    private const POR_PAGINA = 15;

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
            'filtro' => ['nullable', 'string', Rule::in(['todos', 'programados', 'puntuales', 'fallidos'])],
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
            'filtro.in' => 'El filtro no existe.',
            'pagina.integer' => 'La página no es válida.',
            'pagina.min' => 'La página no es válida.',
            'por_pagina.integer' => 'El tamaño de página no es válido.',
            'por_pagina.min' => 'El tamaño de página no es válido.',
            'por_pagina.max' => 'Máximo 100 filas por página.',
        ];
    }

    public function toData(): FiltroRespaldosData
    {
        $filtro = $this->validated('filtro');

        return new FiltroRespaldosData(
            filtro: is_string($filtro) ? $filtro : null,
            pagina: max(1, $this->integer('pagina', 1)),
            porPagina: max(1, $this->integer('por_pagina', self::POR_PAGINA)),
        );
    }
}
