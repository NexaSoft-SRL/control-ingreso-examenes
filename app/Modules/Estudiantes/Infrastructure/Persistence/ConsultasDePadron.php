<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Persistence;

use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use Illuminate\Database\Query\Builder;

/**
 * Lo que comparten las consultas del modulo: la busqueda sin tildes ni
 * mayusculas y la conversion de las filas que devuelve `DB::table`.
 */
trait ConsultasDePadron
{
    /**
     * Cada palabra del texto tiene que aparecer en alguna de las columnas.
     *
     * @param  list<string>  $columnas
     */
    private function buscar(Builder $consulta, ?string $texto, array $columnas): void
    {
        $texto = strtr(mb_strtolower(trim((string) $texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        $palabras = preg_split('/[\s,]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($palabras === false ? [] : $palabras as $palabra) {
            // El escape es «!»: una barra invertida dentro del SQL confunde
            // al analizador de parametros de PDO.
            $patron = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $palabra).'%';

            $consulta->where(function (Builder $alguna) use ($columnas, $patron): void {
                foreach ($columnas as $columna) {
                    $alguna->orWhereRaw(
                        "translate(lower(coalesce({$columna}, '')), 'áéíóúüñ', 'aeiouun') like ? escape '!'",
                        [$patron],
                    );
                }
            });
        }
    }

    /**
     * Condicion «el periodo esta vigente hoy» sobre el alias dado.
     */
    private function vigente(Builder $consulta, string $alias): void
    {
        $hoy = now()->toDateString();

        $consulta
            ->whereDate("{$alias}.fecha_inicio", '<=', $hoy)
            ->whereDate("{$alias}.fecha_fin", '>=', $hoy);
    }

    private function estudiante(object $fila): EstudianteData
    {
        $datos = get_object_vars($fila);

        return new EstudianteData(
            id: $this->entero($datos['id'] ?? null),
            codigoUniversitario: (string) $this->texto($datos['codigo_universitario'] ?? null),
            documentoIdentidad: $this->texto($datos['documento_identidad'] ?? null),
            nombres: (string) $this->texto($datos['nombres'] ?? null),
            apellidos: (string) $this->texto($datos['apellidos'] ?? null),
            verificado: (bool) ($datos['verificado'] ?? false),
        );
    }

    private function nombreCompleto(mixed $apellidos, mixed $nombres): string
    {
        return trim($this->texto($apellidos).', '.$this->texto($nombres), ', ');
    }

    private function texto(mixed $valor): ?string
    {
        return is_scalar($valor) ? (string) $valor : null;
    }

    private function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    private function enteroONulo(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }
}
