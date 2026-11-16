<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use LogicException;

final class PreferenciasAlertasRequest extends FormRequest
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
            'asistencia_minima' => ['required', 'integer', 'between:0,100'],
            'incidencias_minimas' => ['required', 'integer', 'between:1,999'],
        ];
    }

    public function asistenciaMinima(): int
    {
        return $this->enteroValidado('asistencia_minima');
    }

    public function incidenciasMinimas(): int
    {
        return $this->enteroValidado('incidencias_minimas');
    }

    private function enteroValidado(string $campo): int
    {
        $valor = $this->validated($campo);
        if (is_int($valor)) {
            return $valor;
        }

        if (! is_string($valor)) {
            throw new LogicException('La preferencia validada no es un entero.');
        }

        $entero = filter_var($valor, FILTER_VALIDATE_INT);
        if (! is_int($entero)) {
            throw new LogicException('La preferencia validada no es un entero.');
        }

        return $entero;
    }
}
