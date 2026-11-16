<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

final class ReporteConsolidadoRequest extends FormRequest
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
        return [
            'examenes' => ['required', 'array', 'min:2', 'max:20'],
            'examenes.*' => ['required', 'integer', 'min:1', 'distinct', Rule::exists('examenes', 'id')],
            'formato' => ['nullable', Rule::in(['pdf', 'xlsx'])],
        ];
    }

    /**
     * @return list<int>
     */
    public function examenIds(): array
    {
        $ids = $this->validated('examenes');
        if (! is_array($ids)) {
            throw new LogicException('Los exámenes validados no están en formato de lista.');
        }

        $idsValidados = [];

        foreach ($ids as $valor) {
            if (is_int($valor)) {
                $id = $valor;
            } elseif (is_string($valor)) {
                $id = filter_var($valor, FILTER_VALIDATE_INT);
            } else {
                throw new LogicException('El identificador validado no es un entero.');
            }

            if (! is_int($id) || $id < 1) {
                throw new LogicException('El identificador validado no es un entero positivo.');
            }

            $idsValidados[] = $id;
        }

        return $idsValidados;
    }

    public function formato(): string
    {
        return $this->validated('formato') === 'xlsx' ? 'xlsx' : 'pdf';
    }
}
