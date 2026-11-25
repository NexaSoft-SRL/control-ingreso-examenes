<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\AjustePeriodoGateway;
use App\Modules\Academico\Application\DTOs\CalendarioData;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use Illuminate\Support\Facades\DB;

final readonly class EloquentAjustePeriodoGateway implements AjustePeriodoGateway
{
    public function __construct(
        private BitacoraGateway $bitacora,
    ) {}

    public function ajustar(int $periodoId, string $fechaInicio, string $fechaFin, ?int $autorId): bool
    {
        return DB::transaction(function () use ($periodoId, $fechaInicio, $fechaFin, $autorId): bool {
            $periodo = Periodo::query()->lockForUpdate()->find($periodoId);

            if (! $periodo instanceof Periodo) {
                return false;
            }

            $periodo->forceFill([
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ajustado_por' => $autorId,
            ])->save();

            $this->bitacora->registrar(
                $autorId,
                'periodo.ajustar',
                'periodos',
                $periodo->id,
                "Período {$periodo->codigo}: del {$fechaInicio} al {$fechaFin}",
            );

            return true;
        }, 3);
    }

    public function ajustarCalendario(int $periodoId, array $ventanas, ?int $autorId): bool
    {
        return DB::transaction(function () use ($periodoId, $ventanas, $autorId): bool {
            $periodo = Periodo::query()->lockForUpdate()->find($periodoId);

            if (! $periodo instanceof Periodo) {
                return false;
            }

            $actuales = [];

            foreach ($periodo->ventanas ?? [] as $clave => $rango) {
                if (is_string($clave)) {
                    $actuales[$clave] = $rango;
                }
            }

            $periodo->forceFill([
                'ventanas' => [...$actuales, ...$ventanas],
                'ajustado_por' => $autorId,
            ])->save();

            $detalle = implode('; ', array_map(
                static fn (string $clave, array $rango): string => str_replace('_', ' ', $clave).' del '.$rango[0].' al '.$rango[1],
                array_keys($ventanas),
                $ventanas,
            ));

            $this->bitacora->registrar(
                $autorId,
                'periodo.calendario',
                'periodos',
                $periodo->id,
                "Calendario de exámenes del período {$periodo->codigo}: {$detalle}",
            );

            return true;
        }, 3);
    }

    public function restaurarCalendario(int $periodoId, CalendarioData $calendario, ?int $autorId): bool
    {
        return DB::transaction(function () use ($periodoId, $calendario, $autorId): bool {
            $periodo = Periodo::query()->lockForUpdate()->find($periodoId);

            if (! $periodo instanceof Periodo) {
                return false;
            }

            // Lo mismo que hace la deteccion, pero sin la marca de ajuste: pone
            // lo que trae la fuente y quita lo ajustado a mano.
            if ($calendario->inicio !== null && $calendario->fin !== null) {
                $periodo->setAttribute('fecha_inicio', $calendario->inicio);
                $periodo->setAttribute('fecha_fin', $calendario->fin);
                $periodo->fuente = mb_substr($calendario->fuente, 0, 120);
            }

            if ($calendario->ventanas !== []) {
                $periodo->ventanas = $calendario->ventanas;
            }

            $periodo->forceFill(['ajustado_por' => null])->save();

            $this->bitacora->registrar(
                $autorId,
                'periodo.calendario_restaurar',
                'periodos',
                $periodo->id,
                "Calendario del período {$periodo->codigo}: se volvió al de la universidad ({$calendario->fuente})",
            );

            return true;
        }, 3);
    }
}
