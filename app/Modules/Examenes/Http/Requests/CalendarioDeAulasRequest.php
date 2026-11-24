<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Requests;

use App\Modules\Examenes\Application\Queries\ConsultarCalendarioDeAulas;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El rango de dias (hasta dos semanas) y las aulas de la vista: una lista de
 * aulas o un edificio.
 */
final class CalendarioDeAulasRequest extends FormRequest
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
        $limite = $this->limite();

        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:desde',
                ...($limite === null ? [] : ['before_or_equal:'.$limite]),
            ],
            'aulas' => ['nullable', 'array', 'max:'.ConsultarCalendarioDeAulas::MAXIMO_AULAS, 'required_without:edificio_id'],
            'aulas.*' => ['integer', 'min:1'],
            'edificio_id' => ['nullable', 'integer', 'min:1', 'required_without:aulas'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'desde.required' => 'Indique desde qué día.',
            'desde.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'hasta.required' => 'Indique hasta qué día.',
            'hasta.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'No puede ser anterior al inicio.',
            'hasta.before_or_equal' => 'Como máximo '.ConsultarCalendarioDeAulas::MAXIMO_DIAS.' días.',
            'aulas.max' => 'Como máximo '.ConsultarCalendarioDeAulas::MAXIMO_AULAS.' aulas.',
            'aulas.required_without' => 'Elija un aula o un edificio.',
            'aulas.*.integer' => 'El aula no es válida.',
            'aulas.*.min' => 'El aula no es válida.',
            'edificio_id.integer' => 'El edificio no es válido.',
            'edificio_id.required_without' => 'Elija un aula o un edificio.',
        ];
    }

    /**
     * El ultimo dia que admite el rango: trece dias despues del inicio, de
     * modo que una vista no pase de dos semanas. Nulo si el inicio no es una
     * fecha (otra regla lo rechaza).
     */
    private function limite(): ?string
    {
        $desde = $this->input('desde');
        $inicio = is_string($desde) ? DateTimeImmutable::createFromFormat('!Y-m-d', $desde) : false;

        return $inicio === false ? null : $inicio->modify('+'.(ConsultarCalendarioDeAulas::MAXIMO_DIAS - 1).' days')->format('Y-m-d');
    }

    public function texto(string $campo): string
    {
        $valor = $this->validated($campo);

        return is_string($valor) ? $valor : '';
    }

    public function edificio(): ?int
    {
        $valor = $this->validated('edificio_id');

        return is_numeric($valor) ? (int) $valor : null;
    }

    /**
     * @return list<int>
     */
    public function aulas(): array
    {
        $aulas = [];
        $recibidas = $this->validated('aulas');

        foreach (is_array($recibidas) ? $recibidas : [] as $aula) {
            if (is_numeric($aula)) {
                $aulas[] = (int) $aula;
            }
        }

        return array_values(array_unique($aulas));
    }
}
