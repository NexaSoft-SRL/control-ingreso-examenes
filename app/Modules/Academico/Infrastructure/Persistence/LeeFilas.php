<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Ayudas comunes de los gateways de consulta: conversion de columnas,
 * busqueda sin tildes y orden natural.
 */
trait LeeFilas
{
    /**
     * La primera fila de una consulta, o null si no hay ninguna.
     *
     * @param  list<string>  $columnas
     */
    private function primera(Builder $consulta, array $columnas = ['*']): ?stdClass
    {
        $fila = $consulta->first($columnas);

        return $fila instanceof stdClass ? $fila : null;
    }

    private function texto(mixed $valor): ?string
    {
        if (is_string($valor)) {
            return $valor;
        }

        return is_int($valor) || is_float($valor) ? (string) $valor : null;
    }

    private function cadena(mixed $valor): string
    {
        return $this->texto($valor) ?? '';
    }

    private function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    private function enteroONulo(mixed $valor): ?int
    {
        return $valor === null ? null : $this->entero($valor);
    }

    /**
     * Expresion SQL que deja una columna en minusculas y sin tildes.
     */
    private function sinTildes(string $columna): string
    {
        return "translate(lower({$columna}), 'áéíóúüñ', 'aeiouun')";
    }

    /**
     * El patron `LIKE` de un texto de busqueda, en minusculas y sin tildes.
     */
    private function patron(string $buscar): string
    {
        return '%'.addcslashes($this->normalizar($buscar), '%_\\').'%';
    }

    /**
     * En minusculas y sin tildes: para buscar y para ordenar (la base
     * ordena por bytes y dejaria «Álgebra» al final).
     */
    private function normalizar(string $texto): string
    {
        return strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    private function hayTexto(?string $texto): bool
    {
        return $texto !== null && trim($texto) !== '';
    }

    /**
     * El id de una facultad por su clave; 0 (ninguna) si no existe.
     */
    private function facultadId(string $clave): int
    {
        return $this->entero(
            DB::table('facultades')->where('clave', mb_strtolower($clave))->value('id')
        );
    }

    /**
     * @param  list<int>  $periodoIds
     */
    private function enPeriodos(Builder $consulta, string $columna, array $periodoIds): void
    {
        if ($periodoIds !== []) {
            $consulta->whereIn($columna, $periodoIds);
        }
    }

    /**
     * `SEMESTRE 1` -> `Semestre 1`.
     */
    private function nivelLegible(string $nivel): string
    {
        $minusculas = mb_strtolower($nivel);

        return mb_strtoupper(mb_substr($minusculas, 0, 1)).mb_substr($minusculas, 1);
    }

    /**
     * Los horarios de varios grupos, por grupo, de lunes a sabado.
     *
     * @param  list<int>  $grupoIds
     * @return array<int, list<array{dia: string, hora: string, aula: string|null}>>
     */
    private function horariosDe(array $grupoIds): array
    {
        if ($grupoIds === []) {
            return [];
        }

        $filas = DB::table('horarios as h')
            ->leftJoin('aulas as au', 'au.id', '=', 'h.aula_id')
            ->whereIn('h.grupo_id', $grupoIds)
            ->orderByRaw("array_position(array['LU','MA','MI','JU','VI','SA','DO'], h.dia::text)")
            ->orderBy('h.hora_inicio')
            ->orderBy('h.id')
            ->get(['h.grupo_id', 'h.dia', 'h.hora_inicio', 'h.hora_fin', 'au.nombre as aula']);

        $horarios = [];

        foreach ($filas as $fila) {
            $horarios[$this->entero($fila->grupo_id)][] = [
                'dia' => trim($this->cadena($fila->dia)),
                'hora' => substr($this->cadena($fila->hora_inicio), 0, 5)
                    .'-'.substr($this->cadena($fila->hora_fin), 0, 5),
                'aula' => $this->texto($fila->aula),
            ];
        }

        return $horarios;
    }

    /**
     * Cuantos inscritos tiene cada grupo (los que no tienen no aparecen).
     *
     * @param  list<int>  $grupoIds
     * @return array<int, int>
     */
    private function inscritosDe(array $grupoIds): array
    {
        if ($grupoIds === []) {
            return [];
        }

        $filas = DB::table('inscripciones')
            ->whereIn('grupo_id', $grupoIds)
            ->groupBy('grupo_id')
            ->selectRaw('grupo_id, count(*) as total')
            ->get();

        $inscritos = [];

        foreach ($filas as $fila) {
            $inscritos[$this->entero($fila->grupo_id)] = $this->entero($fila->total);
        }

        return $inscritos;
    }
}
