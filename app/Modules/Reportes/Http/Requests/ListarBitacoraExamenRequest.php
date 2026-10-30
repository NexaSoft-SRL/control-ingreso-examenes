<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListarBitacoraExamenRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'categoria' => ['nullable', 'in:INGRESO,RECHAZO,INCIDENCIA,HABILITACION,CODIGO_QR,ACCESO_AULA,ADMINISTRACION'],
            'buscar' => ['nullable', 'string', 'max:120'],
            'aula' => ['nullable', 'integer', 'min:1'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function categoria(): ?string
    {
        return $this->filled('categoria') ? $this->string('categoria')->toString() : null;
    }

    public function buscar(): ?string
    {
        $buscar = trim($this->string('buscar')->toString());

        return $buscar === '' ? null : $buscar;
    }

    public function aula(): ?int
    {
        return $this->filled('aula') ? $this->integer('aula') : null;
    }

    public function pagina(): int
    {
        return $this->integer('pagina', 1);
    }
}
