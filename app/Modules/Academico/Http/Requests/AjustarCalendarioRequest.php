<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AjustarCalendarioRequest extends FormRequest
{
    use ValidaEnEspanol;

    /**
     * Las ventanas del calendario de examenes que se pueden ajustar.
     *
     * @var list<string>
     */
    public const VENTANAS = [
        'primeros_parciales',
        'segundos_parciales',
        'finales_y_segunda_instancia',
        'examenes_de_mesa',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $reglas = ['ventanas' => ['required', 'array', 'min:1']];

        foreach (self::VENTANAS as $clave) {
            $reglas["ventanas.{$clave}"] = ['sometimes', 'array', 'size:2'];
            $reglas["ventanas.{$clave}.0"] = ['required_with:ventanas.'.$clave, 'date_format:Y-m-d'];
            $reglas["ventanas.{$clave}.1"] = ['required_with:ventanas.'.$clave, 'date_format:Y-m-d', 'after_or_equal:ventanas.'.$clave.'.0'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ventanas.required' => 'Indique las fechas del calendario.',
            'ventanas.*.size' => 'Indique desde y hasta.',
            'ventanas.*.0.required_with' => 'Obligatorio',
            'ventanas.*.1.required_with' => 'Obligatorio',
            'ventanas.*.0.date_format' => 'Fecha no válida',
            'ventanas.*.1.date_format' => 'Fecha no válida',
            'ventanas.*.1.after_or_equal' => 'No puede ser anterior al inicio',
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function ventanas(): array
    {
        $ventanas = [];

        foreach (self::VENTANAS as $clave) {
            $rango = $this->input("ventanas.{$clave}");

            if (is_array($rango) && is_string($rango[0] ?? null) && is_string($rango[1] ?? null)) {
                $ventanas[$clave] = [$rango[0], $rango[1]];
            }
        }

        return $ventanas;
    }
}
