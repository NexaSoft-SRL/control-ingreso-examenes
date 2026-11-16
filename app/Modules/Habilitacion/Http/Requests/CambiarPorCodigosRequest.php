<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * «Habilitar» / «Inhabilitar» desde una lista de codigos universitarios
 * pegada en un cuadro de texto: separados por saltos de linea, comas o
 * espacios.
 */
final class CambiarPorCodigosRequest extends FormRequest
{
    public const MAXIMO_CODIGOS = 5000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $reglas = [
            'habilitado' => ['required', 'boolean'],
            'codigos' => ['required', 'string', 'max:100000'],
        ];

        // El motivo solo cuenta al inhabilitar; al habilitar se ignora.
        if ($this->has('habilitado') && ! $this->boolean('habilitado')) {
            $reglas['motivo'] = ['required', 'string', 'min:5', 'max:1000'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'habilitado.required' => 'Falta indicar si se habilita o se inhabilita.',
            'habilitado.boolean' => 'Falta indicar si se habilita o se inhabilita.',
            'codigos.required' => 'Pegue al menos un código',
            'codigos.string' => 'Pegue al menos un código',
            'codigos.max' => 'La lista es demasiado larga',
            'motivo.required' => 'Mínimo 5 caracteres',
            'motivo.string' => 'Mínimo 5 caracteres',
            'motivo.min' => 'Mínimo 5 caracteres',
            'motivo.max' => 'Máximo 1000 caracteres',
        ];
    }

    public function habilitado(): bool
    {
        return $this->boolean('habilitado');
    }

    public function motivo(): ?string
    {
        $motivo = $this->validated('motivo');

        return ! $this->habilitado() && is_string($motivo) ? trim($motivo) : null;
    }

    /**
     * Los codigos del texto, sin repetir; lo que no es un codigo de 5 a 10
     * digitos se ignora.
     *
     * @return list<string>
     */
    public function codigos(): array
    {
        $partes = preg_split('/[^0-9A-Za-z]+/', $this->string('codigos')->toString()) ?: [];
        $codigos = [];

        foreach ($partes as $parte) {
            if (preg_match('/^\d{5,10}$/', $parte) === 1) {
                $codigos[$parte] = true;
            }
        }

        return array_slice(array_map(strval(...), array_keys($codigos)), 0, self::MAXIMO_CODIGOS);
    }
}
