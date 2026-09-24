<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\ConsultaBitacoraGateway;
use App\Modules\Administracion\Application\DTOs\BitacoraOperacionData;
use App\Modules\Administracion\Application\DTOs\ConsultarBitacoraData;
use DateTimeImmutable;
use DateTimeZone;
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
            ]);

        if ($filtros->usuarioId !== null) {
            $query->where(
                'bitacora.usuario_id',
                $filtros->usuarioId,
            );
        }

        if ($filtros->operacion !== null) {
            $query->where(
                'bitacora.operacion',
                $filtros->operacion,
            );
        }

        if ($filtros->fecha !== null) {
            $inicio = new DateTimeImmutable(
                $filtros->fecha.' 00:00:00',
                new DateTimeZone('UTC'),
            );

            $fin = $inicio->modify('+1 day');

            $query
                ->where(
                    'bitacora.fecha_operacion',
                    '>=',
                    $inicio->format('Y-m-d H:i:s'),
                )
                ->where(
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
                operacion: $this->string(
                    $row->operacion,
                    'operacion',
                ),
                tablaAfectada: $this->nullableString(
                    $row->tabla_afectada,
                    'tabla_afectada',
                ),
                registroId: $this->nullableInteger(
                    $row->registro_id,
                    'registro_id',
                ),
                descripcion: $this->nullableString(
                    $row->descripcion,
                    'descripcion',
                ),
                fechaOperacion: $this->string(
                    $row->fecha_operacion,
                    'fecha_operacion',
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
