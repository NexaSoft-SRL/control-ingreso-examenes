<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\IncidenciaIngresoGateway;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentIncidenciaIngresoGateway implements IncidenciaIngresoGateway
{
    public function registrar(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        ?int $usuarioId,
        VeredictoIngreso $veredicto,
        string $detalle,
        ?int $accesoCodigoAulaId = null,
    ): void {
        if ($veredicto->permiteIngreso()) {
            throw new InvalidArgumentException(
                'No se registra una admisión como incidencia de rechazo.'
            );
        }

        $this->insertar([
            'examen_id' => $examenId,
            'aula_id' => $aulaId,
            'estudiante_id' => $estudianteId,
            'tipo' => TipoIncidencia::IntentoNoAutorizado->value,
            'causa' => $veredicto->value,
            'reportada_por' => $usuarioId,
            'acceso_codigo_aula_id' => $accesoCodigoAulaId,
        ], $detalle);
    }

    public function registrarIntento(
        int $aulaId,
        ?int $estudianteId,
        ?int $usuarioId,
        CausaIntento $causa,
        string $detalle,
        ?int $accesoCodigoAulaId = null,
    ): void {
        $this->insertar([
            'examen_id' => null,
            'aula_id' => $aulaId,
            'estudiante_id' => $estudianteId,
            'tipo' => TipoIncidencia::IntentoNoAutorizado->value,
            'causa' => $causa->value,
            'reportada_por' => $usuarioId,
            'acceso_codigo_aula_id' => $accesoCodigoAulaId,
        ], $detalle);
    }

    public function repetidoReciente(
        int $aulaId,
        ?int $estudianteId,
        string $causa,
        string $detalle,
        int $minutos,
    ): bool {
        return DB::table('incidencias')
            ->where('origen', OrigenIncidencia::Sistema->value)
            ->where('aula_id', $aulaId)
            ->where('causa', $causa)
            ->where('detalle', trim($detalle))
            ->where(static function (Builder $consulta) use ($estudianteId): void {
                $estudianteId === null
                    ? $consulta->whereNull('estudiante_id')
                    : $consulta->where('estudiante_id', $estudianteId);
            })
            ->where('ocurrida_en', '>=', now()->subMinutes($minutos))
            ->exists();
    }

    public function registrarIngresoRepetido(
        int $examenId,
        int $aulaId,
        int $estudianteId,
        int $ingresoId,
        ?int $usuarioId,
        ?int $accesoCodigoAulaId,
        string $detalle,
    ): void {
        $this->insertar([
            'examen_id' => $examenId,
            'aula_id' => $aulaId,
            'estudiante_id' => $estudianteId,
            'ingreso_id' => $ingresoId,
            'tipo' => TipoIncidencia::IngresoRepetido->value,
            'causa' => null,
            'reportada_por' => $usuarioId,
            'acceso_codigo_aula_id' => $accesoCodigoAulaId,
        ], $detalle);
    }

    public function ingresoRepetidoReciente(int $ingresoId, int $aulaId, int $minutos): bool
    {
        return DB::table('incidencias')
            ->where('tipo', TipoIncidencia::IngresoRepetido->value)
            ->where('ingreso_id', $ingresoId)
            ->where('aula_id', $aulaId)
            ->where('ocurrida_en', '>=', now()->subMinutes($minutos))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $columnas
     */
    private function insertar(array $columnas, string $detalle): void
    {
        $detalle = trim($detalle);

        if ($detalle === '') {
            throw new InvalidArgumentException(
                'El detalle de la incidencia es obligatorio.'
            );
        }

        $ahora = now();

        DB::table('incidencias')->insert([
            ...$columnas,
            'origen' => OrigenIncidencia::Sistema->value,
            'detalle' => $detalle,
            'ocurrida_en' => $ahora,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
}
