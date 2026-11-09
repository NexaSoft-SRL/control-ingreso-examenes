<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Application\DTOs\ConsultarBitacoraData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConsultarBitacoraRequest extends FormRequest
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
            'buscar' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'operacion' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
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
            'rol.in' => 'El rol no existe.',
            'operacion.max' => 'La operación no es válida.',
            'desde.date_format' => 'La fecha inicial no es válida.',
            'hasta.date_format' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'pagina.integer' => 'La página no es válida.',
            'pagina.min' => 'La página no es válida.',
            'por_pagina.integer' => 'El tamaño de página no es válido.',
            'por_pagina.min' => 'El tamaño de página no es válido.',
            'por_pagina.max' => 'No se pueden pedir más de 100 filas por página.',
        ];
    }

    public function toData(): ConsultarBitacoraData
    {
        return new ConsultarBitacoraData(
            buscar: $this->texto('buscar'),
            rol: $this->texto('rol'),
            operacion: $this->texto('operacion'),
            desde: $this->texto('desde'),
            hasta: $this->texto('hasta'),
            pagina: max(1, $this->integer('pagina', 1)),
            porPagina: max(1, $this->integer('por_pagina', self::POR_PAGINA)),
        );
    }

    private function texto(string $campo): ?string
    {
        $valor = $this->validated($campo);

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
