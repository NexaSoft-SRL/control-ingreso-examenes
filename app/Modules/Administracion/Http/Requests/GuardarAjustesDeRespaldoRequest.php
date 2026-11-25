<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Requests;

use App\Modules\Administracion\Application\DTOs\AjustesDeRespaldoData;
use Illuminate\Foundation\Http\FormRequest;

final class GuardarAjustesDeRespaldoRequest extends FormRequest
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
            'habilitado' => ['required', 'boolean'],
            'hora' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'reintento_minutos' => [
                'sometimes', 'integer',
                'min:'.AjustesDeRespaldoData::REINTENTO_MINIMO,
                'max:'.AjustesDeRespaldoData::REINTENTO_MAXIMO,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hora.regex' => 'Escriba la hora como HH:MM, por ejemplo 03:00.',
            'reintento_minutos.min' => 'Mínimo '.AjustesDeRespaldoData::REINTENTO_MINIMO.' minutos.',
            'reintento_minutos.max' => 'Máximo '.AjustesDeRespaldoData::REINTENTO_MAXIMO.' minutos.',
            'reintento_minutos.integer' => 'Escriba los minutos como un número entero.',
        ];
    }

    /**
     * El reintento es opcional: si no viene, se conserva el actual.
     */
    public function ajustes(AjustesDeRespaldoData $actuales): AjustesDeRespaldoData
    {
        return new AjustesDeRespaldoData(
            habilitado: $this->boolean('habilitado'),
            hora: $this->string('hora')->trim()->toString(),
            reintentoMinutos: $this->has('reintento_minutos')
                ? $this->integer('reintento_minutos')
                : $actuales->reintentoMinutos,
        );
    }
}
