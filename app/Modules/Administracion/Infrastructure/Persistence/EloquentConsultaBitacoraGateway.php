<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\ConsultaBitacoraGateway;
use App\Modules\Administracion\Application\DTOs\BitacoraOperacionData;
use App\Modules\Administracion\Application\DTOs\ConsultarBitacoraData;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentConsultaBitacoraGateway implements ConsultaBitacoraGateway
{
    /**
     * @return list<BitacoraOperacionData>
     */
    public function consultar(
        ConsultarBitacoraData $filtros,
    ): array {
        $query = DB::table('bitacora_operaciones as bitacora')
            ->leftJoin(
                'usuarios as usuario',
                'usuario.id',
                '=',
                'bitacora.usuario_id',
            )
            // Las incidencias de aula viejas viven en `incidencias` con su id
            // anterior (`legado_id`).
            ->leftJoin('incidencias as incidencia', static function (JoinClause $join): void {
                $join->on('incidencia.legado_id', '=', 'bitacora.registro_id')
                    ->where('incidencia.legado_tabla', '=', 'incidencias_aula')
                    ->where('bitacora.tabla_afectada', '=', 'incidencias_aula')
                    ->where('bitacora.operacion', '=', 'INCIDENCIA_AULA_ATENDIDA');
            })
            ->leftJoin('incidencias as incidencia_nueva', static function (JoinClause $join): void {
                $join->on('incidencia_nueva.id', '=', 'bitacora.registro_id')
                    ->where('bitacora.tabla_afectada', '=', 'incidencias');
            })
            ->leftJoin('accesos_codigo_aula as acceso_incidencia', 'acceso_incidencia.id', '=', 'incidencia_nueva.acceso_codigo_aula_id')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'incidencia.aula_id')
            // Quien operó con código de aula no tiene cuenta: se nombra con
            // el nombre que dio al entrar.
            ->leftJoin('accesos_codigo_aula as acceso_directo', static function (JoinClause $join): void {
                $join->on('acceso_directo.id', '=', 'bitacora.registro_id')
                    ->where('bitacora.tabla_afectada', '=', 'accesos_codigo_aula');
            })
            ->leftJoin('ingresos as ingreso', static function (JoinClause $join): void {
                $join->on('ingreso.id', '=', 'bitacora.registro_id')
                    ->where('bitacora.tabla_afectada', '=', 'ingresos');
            })
            ->leftJoin('accesos_codigo_aula as acceso_ingreso', 'acceso_ingreso.id', '=', 'ingreso.acceso_codigo_aula_id')
            // La cuenta afectada se muestra por su usuario, no por su id.
            ->leftJoin('usuarios as afectado', static function (JoinClause $join): void {
                $join->on('afectado.id', '=', 'bitacora.registro_id')
                    ->where('bitacora.tabla_afectada', '=', 'usuarios');
            })
            ->select([
                'bitacora.id',
                'bitacora.usuario_id',
                'usuario.nombre as usuario_nombre',
                'usuario.correo as usuario_email',
                'bitacora.operacion',
                'bitacora.tabla_afectada',
                'bitacora.registro_id',
                'bitacora.descripcion',
                'bitacora.fecha_operacion',
                'incidencia.examen_id as incidencia_examen_id',
                'aula.nombre as incidencia_aula',
                'afectado.usuario as registro_usuario',
            ])
            ->selectRaw('coalesce(acceso_directo.nombre_colaborador, acceso_ingreso.nombre_colaborador, acceso_incidencia.nombre_colaborador) as colaborador');

        if ($filtros->usuarioId !== null) {
            $query->where(
                'bitacora.usuario_id',
                $filtros->usuarioId,
            );
        }

        if ($filtros->operacion !== null) {
            // La solicitud del enlace tiene su propia opcion en el filtro.
            if ($filtros->operacion === 'contrasena.restablecimiento') {
                $query->whereIn('bitacora.operacion', [
                    'contrasena.restablecer_por_enlace',
                    'usuario.restablecer_temporal',
                ]);
            } else {
                $query->where(
                    'bitacora.operacion',
                    $filtros->operacion,
                );
            }
        }

        // Un solo dia es el rango que empieza y termina en el mismo dia.
        $desde = $filtros->desde ?? $filtros->fecha;
        $hasta = $filtros->hasta ?? $filtros->fecha;

        if ($desde !== null) {
            $inicio = new DateTimeImmutable(
                $desde.' 00:00:00',
                new DateTimeZone('UTC'),
            );

            $query->where(
                'bitacora.fecha_operacion',
                '>=',
                $inicio->format('Y-m-d H:i:s'),
            );
        }

        if ($hasta !== null) {
            $fin = (new DateTimeImmutable(
                $hasta.' 00:00:00',
                new DateTimeZone('UTC'),
            ))->modify('+1 day');

            $query->where(
                'bitacora.fecha_operacion',
                '<',
                $fin->format('Y-m-d H:i:s'),
            );
        }

        $rows = $query
            ->orderByDesc('bitacora.fecha_operacion')
            ->orderByDesc('bitacora.id')
            ->get();

        $resultado = [];

        foreach ($rows as $row) {
            $operacion = $this->string(
                $row->operacion,
                'operacion',
            );
            $descripcion = $this->nullableString(
                $row->descripcion,
                'descripcion',
            );
            $incidenciaAula = $this->nullableString(
                $row->incidencia_aula,
                'incidencia_aula',
            );
            $incidenciaExamenId = $this->nullableInteger(
                $row->incidencia_examen_id,
                'incidencia_examen_id',
            );

            if (
                $operacion === 'INCIDENCIA_AULA_ATENDIDA'
                && $incidenciaAula !== null
                && $incidenciaExamenId !== null
            ) {
                $descripcion = sprintf(
                    'Incidencia de %s, examen %d.',
                    $incidenciaAula,
                    $incidenciaExamenId,
                );
            }

            $resultado[] = new BitacoraOperacionData(
                id: $this->integer($row->id, 'id'),
                usuarioId: $this->nullableInteger(
                    $row->usuario_id,
                    'usuario_id',
                ),
                usuarioNombre: $this->nullableString(
                    $row->usuario_nombre,
                    'usuario_nombre',
                ),
                usuarioEmail: $this->nullableString(
                    $row->usuario_email,
                    'usuario_email',
                ),
                operacion: $operacion,
                tablaAfectada: $this->nullableString(
                    $row->tabla_afectada,
                    'tabla_afectada',
                ),
                registroId: $this->nullableInteger(
                    $row->registro_id,
                    'registro_id',
                ),
                descripcion: $descripcion,
                fechaOperacion: $this->string(
                    $row->fecha_operacion,
                    'fecha_operacion',
                ),
                colaborador: $this->nullableString(
                    $row->colaborador,
                    'colaborador',
                ),
                registroEtiqueta: $this->nullableString(
                    $row->registro_usuario,
                    'registro_usuario',
                ),
            );
        }

        return $resultado;
    }

    private function integer(
        mixed $value,
        string $column,
    ): int {
        if (is_int($value)) {
            return $value;
        }

        if (
            is_string($value)
            && preg_match('/^-?\d+$/', $value) === 1
        ) {
            return (int) $value;
        }

        throw new LogicException(
            "La columna {$column} no contiene un entero válido."
        );
    }

    private function nullableInteger(
        mixed $value,
        string $column,
    ): ?int {
        if ($value === null) {
            return null;
        }

        return $this->integer($value, $column);
    }

    private function string(
        mixed $value,
        string $column,
    ): string {
        if (is_string($value)) {
            return $value;
        }

        throw new LogicException(
            "La columna {$column} no contiene una cadena válida."
        );
    }

    private function nullableString(
        mixed $value,
        string $column,
    ): ?string {
        if ($value === null) {
            return null;
        }

        return $this->string($value, $column);
    }
}
