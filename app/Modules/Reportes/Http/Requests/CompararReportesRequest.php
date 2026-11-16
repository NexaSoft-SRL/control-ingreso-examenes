<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

final class CompararReportesRequest extends FormRequest
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
            'examenes' => ['nullable', 'array', 'min:2', 'max:20', 'required_without:periodos'],
            'examenes.*' => ['required', 'integer', 'min:1', 'distinct', Rule::exists('examenes', 'id')],
            'periodos' => ['nullable', 'array', 'min:2', 'max:10', 'required_without:examenes'],
            'periodos.*' => ['required', 'integer', 'min:1', 'distinct', Rule::exists('periodos', 'id')],
        ];
    }

    /**
     * @return list<int>
     */
    public function examenIds(): array
    {
        return $this->ids('examenes');
    }

    /**
     * @return list<int>
     */
    public function periodoIds(): array
    {
        return $this->ids('periodos');
    }

    /**
     * @return list<int>
     */
    private function ids(string $campo): array
    {
        $valores = $this->validated($campo, []);
        if (! is_array($valores)) {
            return [];
        }

        $ids = [];

        foreach ($valores as $valor) {
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

            $ids[] = $id;
        }

        return $ids;
    }
}
