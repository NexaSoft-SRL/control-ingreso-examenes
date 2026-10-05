<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\AmbienteExamenGateway;
use App\Modules\Examenes\Application\DTOs\AmbienteAsignadoData;
use App\Modules\Examenes\Application\DTOs\OcupacionExamenData;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentAmbienteExamenGateway implements AmbienteExamenGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    /**
     * @return list<AmbienteAsignadoData>
     */
    public function listarDeExamen(int $examenId): array
    {
        $filas = DB::table('examen_ambiente as asignado')
            ->join('ambientes as ambiente', 'ambiente.id', '=', 'asignado.ambiente_id')
            ->where('asignado.examen_id', $examenId)
            ->orderBy('ambiente.nombre')
            ->select([
                'asignado.id',
                'ambiente.id as ambiente_id',
                'ambiente.nombre',
                'ambiente.ubicacion',
                'ambiente.capacidad',
                'ambiente.estado',
            ])
            ->get();

        $ambientes = [];

        foreach ($filas as $fila) {
            $ambientes[] = new AmbienteAsignadoData(
                id: $this->entero($fila->id),
                ambienteId: $this->entero($fila->ambiente_id),
                nombre: $this->texto($fila->nombre) ?? '',
                ubicacion: $this->texto($fila->ubicacion),
                capacidad: $this->entero($fila->capacidad),
                estado: $this->texto($fila->estado) ?? '',
            );
        }

        return $ambientes;
    }

    public function estaDisponible(int $examenId, int $ambienteId): bool
    {
        $estado = DB::table('ambientes')->where('id', $ambienteId)->value('estado');

        // Un ambiente en mantenimiento u ocupado no se elige para un examen.
        if ($this->texto($estado) === 'MANTENIMIENTO') {
            throw new AmbienteNoDisponibleException('El ambiente está en mantenimiento.');
        }

        if ($this->texto($estado) === 'INACTIVO') {
            throw new AmbienteNoDisponibleException('El ambiente está inactivo.');
        }

        if ($this->texto($estado) !== 'DISPONIBLE') {
            throw new AmbienteNoDisponibleException('El ambiente no está disponible.');
        }

        $examen = DB::table('examenes')->where('id', $examenId)->first();

        if ($examen === null) {
            return false;
        }

        /** @var array<string, mixed> $columnas */
        $columnas = get_object_vars($examen);

        return ! $this->tieneExamenSolapado($columnas, $ambienteId);
    }

    public function asignar(
        int $examenId,
        int $ambienteId,
        ?int $usuarioId,
    ): AmbienteAsignadoData {
        DB::transaction(function () use ($examenId, $ambienteId, $usuarioId): void {
            DB::table('examen_ambiente')->insert([
                'examen_id' => $examenId,
                'ambiente_id' => $ambienteId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->bitacora->registrar(
                $usuarioId,
                'examen.asignar_ambiente',
                'examen_ambiente',
                $examenId,
                sprintf('Ambiente %d asignado al examen %d.', $ambienteId, $examenId),
            );
        }, 3);

        foreach ($this->listarDeExamen($examenId) as $asignado) {
            if ($asignado->ambienteId === $ambienteId) {
                return $asignado;
            }
        }

        throw new LogicException('El ambiente asignado no se pudo recuperar.');
    }

    public function quitar(
        int $examenId,
        int $ambienteId,
        ?int $usuarioId,
    ): bool {
        $existe = DB::table('examen_ambiente')
            ->where('examen_id', $examenId)
            ->where('ambiente_id', $ambienteId)
            ->exists();

        if (! $existe) {
            return false;
        }

        DB::transaction(function () use ($examenId, $ambienteId, $usuarioId): void {
            DB::table('examen_ambiente')
                ->where('examen_id', $examenId)
                ->where('ambiente_id', $ambienteId)
                ->delete();

            $this->bitacora->registrar(
                $usuarioId,
                'examen.quitar_ambiente',
                'examen_ambiente',
                $examenId,
                sprintf('Ambiente %d quitado del examen %d.', $ambienteId, $examenId),
            );
        }, 3);

        return true;
    }

    public function ocupacion(int $examenId): OcupacionExamenData
    {
        $capacidad = DB::table('examen_ambiente as asignado')
            ->join('ambientes as ambiente', 'ambiente.id', '=', 'asignado.ambiente_id')
            ->where('asignado.examen_id', $examenId)
            ->sum('ambiente.capacidad');

        // Las habilitaciones llegan en HU-11: hasta entonces el examen no
        // tiene habilitados y la comparacion da cero.
        $habilitados = DB::table('habilitaciones_examen')
            ->where('examen_id', $examenId)
            ->where('estado', 'HABILITADO')
            ->count();

        return new OcupacionExamenData(
            capacidadAsignada: (int) $capacidad,
            habilitados: $habilitados,
        );
    }

    /**
     * Dos examenes se solapan si comparten ambiente y sus franjas de inicio
     * y fin se cruzan.
     *
     * @param  array<string, mixed>  $examen
     */
    private function tieneExamenSolapado(array $examen, int $ambienteId): bool
    {
        $fecha = $this->texto($examen['fecha'] ?? null) ?? '';
        $desde = new DateTimeImmutable($fecha.' '.($this->texto($examen['hora_inicio'] ?? null) ?? ''));
        $hasta = $desde->modify(
            '+'.$this->entero($examen['duracion_minutos'] ?? 0).' minutes'
        );

        $otros = DB::table('examen_ambiente as asignado')
            ->join('examenes as otro', 'otro.id', '=', 'asignado.examen_id')
            ->where('asignado.ambiente_id', $ambienteId)
            ->where('otro.id', '!=', $this->entero($examen['id'] ?? 0))
            ->where('otro.fecha', $fecha)
            ->select(['otro.hora_inicio', 'otro.duracion_minutos', 'otro.fecha'])
            ->get();

        foreach ($otros as $fila) {
            /** @var array<string, mixed> $otro */
            $otro = get_object_vars($fila);

            $otroInicio = new DateTimeImmutable(
                ($this->texto($otro['fecha'] ?? null) ?? '')
                .' '.($this->texto($otro['hora_inicio'] ?? null) ?? '')
            );
            $otroFin = $otroInicio->modify(
                '+'.$this->entero($otro['duracion_minutos'] ?? 0).' minutes'
            );

            if ($desde < $otroFin && $otroInicio < $hasta) {
                return true;
            }
        }

        return false;
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_string($valor)) {
            return $valor;
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        throw new LogicException('La columna no contiene un texto válido.');
    }

    private function entero(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && preg_match('/^-?\d+$/', $valor) === 1) {
            return (int) $valor;
        }

        throw new LogicException('La columna no contiene un entero válido.');
    }
}
