<?php

declare(strict_types=1);

namespace App\Modules\Academico\Http\Requests;

use App\Modules\Academico\Application\DTOs\PaginaData;

/**
 * Lo comun de las peticiones del modulo: mensajes en espanol, lectura de
 * filtros opcionales y pagina.
 */
trait ValidaEnEspanol
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'max' => 'El campo :attribute no puede pasar de :max.',
            'string' => 'El campo :attribute debe ser un texto.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'exists' => 'La facultad indicada no existe.',
            'date_format' => 'El campo :attribute debe ser una fecha con el formato AAAA-MM-DD.',
            'after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'email' => 'El correo no tiene un formato válido.',
            'regex' => 'El usuario solo admite letras minúsculas, números, punto, guion y guion bajo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pagina' => 'página',
            'por_pagina' => 'por página',
            'periodo' => 'período',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function reglasDePagina(): array
    {
        return [
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function pagina(int $porPaginaPorDefecto): PaginaData
    {
        return new PaginaData(
            pagina: $this->enteroOpcional('pagina') ?? 1,
            porPagina: $this->enteroOpcional('por_pagina') ?? $porPaginaPorDefecto,
        );
    }

    protected function enteroOpcional(string $campo): ?int
    {
        $valor = $this->validated($campo);

        return is_numeric($valor) ? (int) $valor : null;
    }

    protected function textoOpcional(string $campo): ?string
    {
        $valor = $this->validated($campo);

        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    protected function marcado(string $campo): bool
    {
        return filter_var($this->validated($campo), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * La clave de facultad se compara en minusculas.
     */
    protected function facultadEnMinusculas(): void
    {
        $facultad = $this->input('facultad');

        if (is_string($facultad)) {
            $this->merge(['facultad' => mb_strtolower(trim($facultad))]);
        }
    }
}
