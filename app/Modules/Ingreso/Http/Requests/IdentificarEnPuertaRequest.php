<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Application\Queries\ResolverIdentificador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un codigo QR (`tipo` = qr) o lo escrito en el campo «Código SIS o C.I.»:
 * el codigo universitario, o el C.I. con su complemento opcional
 * («5003748», «5003748-1A», «5003748 1A»). `codigo` y `documento` siguen
 * valiendo para elegir la busqueda.
 */
final class IdentificarEnPuertaRequest extends FormRequest
{
    private const CODIGO_O_CI = '/^\d{5,10}([\s-][0-9A-Za-z]{1,3}|[A-Za-z][0-9A-Za-z]{0,2})?$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $tipo = $this->tipo();

        return [
            'tipo' => ['nullable', 'string', Rule::in(['codigo', 'documento', 'qr', ResolverIdentificador::UNICO])],
            'identificador' => [
                'required',
                'string',
                'max:2048',
                ...match ($tipo) {
                    'qr' => [],
                    ResolverIdentificador::UNICO => ['regex:'.self::CODIGO_O_CI],
                    default => ['regex:/\A[0-9]{5,10}\z/D'],
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'Tipo de identificación no válido',
            'identificador.required' => 'Obligatorio',
            'identificador.string' => 'Identificación no válida',
            'identificador.max' => 'Código QR no válido',
            'identificador.regex' => $this->tipo() === ResolverIdentificador::UNICO
                ? 'Código SIS o C.I. no válido'
                : 'Debe contener entre 5 y 10 dígitos.',
        ];
    }

    public function tipo(): string
    {
        $tipo = $this->input('tipo');

        return is_string($tipo) && $tipo !== '' ? $tipo : ResolverIdentificador::UNICO;
    }

    public function identificador(): string
    {
        return trim($this->string('identificador')->toString());
    }
}
