<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Export;

use App\Modules\Administracion\Application\Contracts\ExportadorBaseDeDatos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;

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

        $zona = is_string(config('umss.zona_horaria')) ? config('umss.zona_horaria') : 'America/La_Paz';
        $fecha = now($zona)->format('Y-m-d H:i:s');

        $tablas = $this->obtenerTablas($driver);
        $tablasExcluidas = $this->obtenerTablasExcluidas();

        $tablasAExportar = array_values(array_filter(
            $tablas,
            static fn (string $tabla): bool => ! in_array(strtolower($tabla), $tablasExcluidas, true)
        ));

        $sql = [];
        $sql[] = '-- ============================================================';
        $sql[] = '-- Respaldo de Base de Datos - Sistema de Control de Ingreso';
        $sql[] = "-- Fecha: {$fecha}";
        $sql[] = "-- Tipo: {$tipo}";
        $sql[] = "-- Versión Esquema: {$versionEsquema}";
        $sql[] = '-- ============================================================';
        $sql[] = '';

        if ($isPgsql) {
            $sql[] = "SET session_replication_role = 'replica';";
            $sql[] = 'BEGIN;';
            $sql[] = '';
        } else {
            $sql[] = 'PRAGMA foreign_keys = OFF;';
            $sql[] = 'BEGIN TRANSACTION;';
            $sql[] = '';
        }

        foreach ($tablasAExportar as $tabla) {
            /** @var list<string> $columnas */
            $columnas = array_values(array_filter(Schema::getColumnListing($tabla), 'is_string'));
            if (empty($columnas)) {
                continue;
            }

            $consulta = DB::table($tabla);
            if (in_array('id', $columnas, true)) {
                $consulta->orderBy('id');
            }

            $filas = $consulta->get()->all();
            $totalFilas = count($filas);

            $sql[] = '-- ------------------------------------------------------------';
            $sql[] = "-- Tabla: \"{$tabla}\" ({$totalFilas} registros)";
            $sql[] = '-- ------------------------------------------------------------';
            $sql[] = "DELETE FROM \"{$tabla}\";";

            if ($totalFilas > 0) {
                $columnasSql = implode(', ', array_map(
                    static fn (mixed $col): string => '"'.(string) $col.'"',
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
                $seqResult = DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') as seq", [$tabla]);
                if ($seqResult !== null && $seqResult->seq !== null) {
                    $sql[] = "SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), coalesce(max(id), 1), max(id) IS NOT NULL) FROM \"{$tabla}\";";
                }
            }

            $sql[] = '';
        }

        if ($isPgsql) {
            $sql[] = 'COMMIT;';
            $sql[] = "SET session_replication_role = 'origin';";
        } else {
            $sql[] = 'COMMIT;';
            $sql[] = 'PRAGMA foreign_keys = ON;';
        }
        $sql[] = '';

        return implode("\n", $sql);
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
        $tablas = array_values(array_filter(Schema::getTableListing(), 'is_string'));
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
            static fn (mixed $nombre): string => is_string($nombre) ? strtolower($nombre) : '',
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
            $json = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $pdo->quote($json !== false ? $json : '{}');
        }

        return 'NULL';
    }
}
