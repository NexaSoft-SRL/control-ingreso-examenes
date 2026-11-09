<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Un codigo QR (`token`) o un codigo universitario o documento
 * (`identificador`), uno de los dos.
 */
final class VerificarRequest extends FormRequest
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
            'token' => ['required_without:identificador', 'nullable', 'string', 'max:64'],
            // Codigo universitario, o C.I. con su complemento opcional
            // («5003748», «5003748-1A», «5003748 1A»).
            'identificador' => ['required_without:token', 'nullable', 'string', 'regex:/^\d{5,10}([\s-][0-9A-Za-z]{1,3}|[A-Za-z][0-9A-Za-z]{0,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required_without' => 'Lea un código QR o escriba el código del estudiante.',
            'token.string' => 'El código QR no es válido.',
            'token.max' => 'El código QR no es válido.',
            'identificador.required_without' => 'Código o C.I. no válido',
            'identificador.string' => 'Código o C.I. no válido',
            'identificador.regex' => 'Código o C.I. no válido',
        ];
    }

    public function token(): ?string
    {
        $token = $this->validated('token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function identificador(): ?string
    {
        $identificador = $this->validated('identificador');

        return is_string($identificador) && trim($identificador) !== ''
            ? mb_strtoupper(trim($identificador))
            : null;
    }
}
