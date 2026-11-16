<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Http\Controllers;

use App\Modules\Reportes\Application\DTOs\BitacoraExamenData;
use App\Modules\Reportes\Application\DTOs\ControlDeAula;
use App\Modules\Reportes\Application\DTOs\SucesoExamen;
use App\Modules\Reportes\Domain\Enums\GravedadIncidencia;
use App\Modules\Reportes\Http\Requests\FiltrosBitacoraExamenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * La bitacora de un examen, igual para el docente y para el administrador.
 */
trait RespondeBitacora
{
    /**
     * @param  BitacoraExamenData|null  $bitacora  null si el examen no existe
     */
    private function bitacoraJson(
        ?BitacoraExamenData $bitacora,
        FiltrosBitacoraExamenRequest $request,
    ): JsonResponse {
        if ($bitacora === null) {
            return response()->json([
                'message' => 'Examen no encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        $filtros = $request->filtros();
        $conteosCategorias = [];
        $conteosGravedad = [];
        $aulas = [];

        foreach ($bitacora->sucesos as $suceso) {
            $conteosCategorias[$suceso->categoria] = ($conteosCategorias[$suceso->categoria] ?? 0) + 1;
            if ($suceso->gravedad !== null) {
                $conteosGravedad[$suceso->gravedad] = ($conteosGravedad[$suceso->gravedad] ?? 0) + 1;
            }
            if ($suceso->aula !== null) {
                $aulas[$suceso->aula] = true;
            }
        }

        $busqueda = $this->normalizarBusqueda($filtros['buscar'] ?? '');
        $sucesos = array_values(array_filter(
            $bitacora->sucesos,
            fn (SucesoExamen $suceso): bool => (
                ($filtros['categoria'] === null || $suceso->categoria === $filtros['categoria'])
                && ($filtros['gravedad'] === null || $suceso->gravedad === $filtros['gravedad'])
                && ($filtros['aula'] === null || $suceso->aula === $filtros['aula'])
                && ($busqueda === '' || $this->coincideBusqueda($suceso, $busqueda))
            ),
        ));

        $total = count($sucesos);
        $porPagina = $filtros['por_pagina'];
        $pagina = $filtros['pagina'];
        $offset = $pagina > intdiv(PHP_INT_MAX, $porPagina)
            ? $total
            : min($total, ($pagina - 1) * $porPagina);
        $sucesos = array_slice($sucesos, $offset, $porPagina);
        $aulas = array_keys($aulas);
        sort($aulas, SORT_NATURAL);

        return response()->json([
            // Quien controlo cada aula.
            'aulas' => array_map(
                static fn (ControlDeAula $aula): array => [
                    'aula' => $aula->aula,
                    'personal' => $aula->personal,
                    'con_codigo' => $aula->conCodigo,
                    'controlo' => $aula->quienes(),
                    'ingresos' => $aula->ingresos,
                    'primer_ingreso' => $aula->primerIngreso,
                    'ultimo_ingreso' => $aula->ultimoIngreso,
                ],
                $bitacora->aulas,
            ),
            'data' => array_map(
                static fn (SucesoExamen $suceso): array => [
                    'instante' => $suceso->instante,
                    'categoria' => $suceso->categoria,
                    'titulo' => $suceso->titulo,
                    'detalle' => $suceso->detalle,
                    'aula' => $suceso->aula,
                    'estudiante' => $suceso->estudiante,
                    'codigo' => $suceso->codigo,
                    'por' => $suceso->por,
                    'gravedad' => $suceso->gravedad,
                    'gravedad_texto' => $suceso->gravedad === null
                        ? null
                        : GravedadIncidencia::from($suceso->gravedad)->etiqueta(),
                ],
                $sucesos,
            ),
            'meta' => [
                'total' => $total,
                'total_general' => count($bitacora->sucesos),
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'categorias' => $conteosCategorias,
                'gravedades' => $conteosGravedad,
                'aulas' => $aulas,
            ],
        ]);
    }

    private function coincideBusqueda(SucesoExamen $suceso, string $busqueda): bool
    {
        $texto = $this->normalizarBusqueda(implode(' ', array_filter([
            $suceso->instante,
            $suceso->categoria,
            $suceso->titulo,
            $suceso->detalle,
            $suceso->aula,
            $suceso->estudiante,
            $suceso->codigo,
            $suceso->por,
        ])));

        return str_contains($texto, $busqueda);
    }

    private function normalizarBusqueda(string $texto): string
    {
        $normalizado = Str::ascii($texto);
        $normalizado = mb_strtolower($normalizado);

        return preg_replace('/\s+/', ' ', trim($normalizado)) ?? '';
    }
}
