<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Export;

use App\Modules\Administracion\Application\Contracts\ExportadorBaseDeDatos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;

/**
 * HU-25: Exportador SQL en PHP que genera el volcado de la base de datos
 * excluyendo tablas de trabajo transitorio y garantizando restauración segura.
 */
final class ExportadorSqlBaseDeDatos implements ExportadorBaseDeDatos
{
    /**
     * @var list<string>
     */
    private const TABLAS_EXCLUIDAS_POR_DEFECTO = [
        'respaldos',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'job_batches',
        'migrations',
        'presencias_puerta',
    ];

    public function exportar(string $versionEsquema, string $tipo): string
    {
        $driver = DB::connection()->getDriverName();
        $isPgsql = $driver === 'pgsql';
        $pdo = DB::connection()->getPdo();

        $zona = is_string(config('umss.zona_horaria'))
            ? config('umss.zona_horaria')
            : 'America/La_Paz';

        $fecha = now($zona)->format('Y-m-d H:i:s');

        $tablas = $this->obtenerTablas($driver);
        $tablasExcluidas = $this->obtenerTablasExcluidas();

        $tablasAExportar = array_values(array_filter(
            $tablas,
            static fn (string $tabla): bool => ! in_array(
                strtolower($tabla),
                $tablasExcluidas,
                true
            )
        ));

        $tablasParaInsertar = $isPgsql
            ? $this->ordenarTablasPorDependencias($tablasAExportar)
            : $tablasAExportar;

        $tablasParaEliminar = array_reverse($tablasParaInsertar);

        $sql = [];
        $sql[] = '-- ============================================================';
        $sql[] = '-- Respaldo de Base de Datos - Sistema de Control de Ingreso';
        $sql[] = "-- Fecha: {$fecha}";
        $sql[] = "-- Tipo: {$tipo}";
        $sql[] = "-- Versión Esquema: {$versionEsquema}";
        $sql[] = '-- ============================================================';
        $sql[] = '';

        if ($isPgsql) {
            $sql[] = 'BEGIN;';
        } else {
            $sql[] = 'PRAGMA foreign_keys = OFF;';
            $sql[] = 'BEGIN TRANSACTION;';
        }

        $sql[] = '';
        $sql[] = '-- ============================================================';
        $sql[] = '-- FASE 1: ELIMINACIÓN DE REGISTROS';
        $sql[] = '-- ============================================================';
        $sql[] = '';

        foreach ($tablasParaEliminar as $tabla) {
            $totalFilas = DB::table($tabla)->count();

            $sql[] = "-- Tabla: \"{$tabla}\" ({$totalFilas} registros)";
            $sql[] = "DELETE FROM \"{$tabla}\";";
            $sql[] = '';
        }

        $sql[] = '-- ============================================================';
        $sql[] = '-- FASE 2: INSERCIÓN DE REGISTROS';
        $sql[] = '-- ============================================================';
        $sql[] = '';

        foreach ($tablasParaInsertar as $tabla) {
            /** @var list<string> $columnas */
            $columnas = array_values(array_filter(
                Schema::getColumnListing($tabla),
                'is_string'
            ));

            if (empty($columnas)) {
                continue;
            }

            $consulta = DB::table($tabla);

            if (in_array('id', $columnas, true)) {
                $consulta->orderBy('id');
            }

            $filas = $consulta->get()->all();
            $totalFilas = count($filas);

            $sql[] = "-- Tabla: \"{$tabla}\" ({$totalFilas} registros)";

            if ($totalFilas > 0) {
                $columnasSql = implode(', ', array_map(
                    static fn (string $col): string => '"'.$col.'"',
                    $columnas
                ));

                foreach (array_chunk($filas, 100) as $lote) {
                    $tuplas = [];

                    foreach ($lote as $fila) {
                        /** @var array<string, mixed> $filaArr */
                        $filaArr = (array) $fila;
                        $valores = [];

                        foreach ($columnas as $col) {
                            $valor = $filaArr[$col] ?? null;
                            $valores[] = $this->escaparValor($pdo, $valor);
                        }

                        $tuplas[] = '('.implode(', ', $valores).')';
                    }

                    $sql[] = "INSERT INTO \"{$tabla}\" ({$columnasSql}) VALUES";
                    $sql[] = '  '.implode(",\n  ", $tuplas).';';
                }
            }

            if ($isPgsql && in_array('id', $columnas, true)) {
                /** @var object{seq: string|null}|null $seqResult */
                $seqResult = DB::selectOne(
                    "SELECT pg_get_serial_sequence(?, 'id') as seq",
                    [$tabla]
                );

                if ($seqResult !== null && $seqResult->seq !== null) {
                    $tablaSql = str_replace("'", "''", $tabla);

                    $sql[] = "SELECT setval(pg_get_serial_sequence('{$tablaSql}', 'id'), coalesce(max(id), 1), max(id) IS NOT NULL) FROM \"{$tabla}\";";
                }
            }

            $sql[] = '';
        }

        $sql[] = 'COMMIT;';

        if (! $isPgsql) {
            $sql[] = 'PRAGMA foreign_keys = ON;';
        }

        $sql[] = '';

        return implode("\n", $sql);
    }

    /**
     * Calcula un orden de tablas respetando las claves foráneas.
     *
     * Las tablas padre aparecen antes que las tablas hijas.
     *
     * @param  list<string>  $tablas
     * @return list<string>
     */
    private function ordenarTablasPorDependencias(array $tablas): array
    {
        if (empty($tablas)) {
            return [];
        }

        /** @var array<string, true> $tablasIncluidas */
        $tablasIncluidas = array_fill_keys($tablas, true);

        /** @var array<string, int> $gradosEntrada */
        $gradosEntrada = [];

        /** @var array<string, list<string>> $hijasPorPadre */
        $hijasPorPadre = [];

        foreach ($tablas as $tabla) {
            $gradosEntrada[$tabla] = 0;
            $hijasPorPadre[$tabla] = [];
        }

        $relaciones = DB::select(
            "SELECT
                conrelid::regclass::text AS tabla_hija,
                confrelid::regclass::text AS tabla_padre
             FROM pg_constraint
             WHERE contype = 'f'
               AND connamespace = 'public'::regnamespace"
        );

        foreach ($relaciones as $relacion) {
            $hija = (string) $relacion->tabla_hija;
            $padre = (string) $relacion->tabla_padre;

            if (
                ! isset($tablasIncluidas[$hija])
                || ! isset($tablasIncluidas[$padre])
            ) {
                continue;
            }

            if (in_array($hija, $hijasPorPadre[$padre], true)) {
                continue;
            }

            $hijasPorPadre[$padre][] = $hija;
            $gradosEntrada[$hija]++;
        }

        $disponibles = [];

        foreach ($gradosEntrada as $tabla => $grado) {
            if ($grado === 0) {
                $disponibles[] = $tabla;
            }
        }

        sort($disponibles);

        $orden = [];

        while (! empty($disponibles)) {
            $tabla = array_shift($disponibles);
            $orden[] = $tabla;

            $hijas = $hijasPorPadre[$tabla];
            sort($hijas);

            foreach ($hijas as $hija) {
                $gradosEntrada[$hija]--;

                if ($gradosEntrada[$hija] === 0) {
                    $disponibles[] = $hija;
                }
            }

            sort($disponibles);
        }

        if (count($orden) !== count($tablas)) {
            throw new RuntimeException(
                'No se pudo determinar el orden de restauración: '
                .'se detectó una dependencia circular entre tablas.'
            );
        }

        return $orden;
    }

    /**
     * @return list<string>
     */
    private function obtenerTablas(string $driver): array
    {
        if ($driver === 'pgsql') {
            /** @var list<string> $tablas */
            $tablas = DB::table('pg_tables')
                ->where('schemaname', 'public')
                ->orderBy('tablename')
                ->pluck('tablename')
                ->all();

            return $tablas;
        }

        /** @var list<string> $tablas */
        $tablas = array_values(array_filter(
            Schema::getTableListing(),
            'is_string'
        ));

        sort($tablas);

        return $tablas;
    }

    /**
     * @return list<string>
     */
    private function obtenerTablasExcluidas(): array
    {
        $configuradas = config('respaldo.tablas_excluidas');

        if (! is_array($configuradas) || empty($configuradas)) {
            $configuradas = self::TABLAS_EXCLUIDAS_POR_DEFECTO;
        }

        return array_values(array_map(
            static fn (mixed $nombre): string => is_string($nombre)
                ? strtolower($nombre)
                : '',
            $configuradas
        ));
    }

    private function escaparValor(PDO $pdo, mixed $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }

        if (is_bool($valor)) {
            return $valor ? 'TRUE' : 'FALSE';
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        if (is_string($valor)) {
            return $pdo->quote($valor);
        }

        if (is_array($valor) || is_object($valor)) {
            $json = json_encode(
                $valor,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            return $pdo->quote($json !== false ? $json : '{}');
        }

        return 'NULL';
    }
}
