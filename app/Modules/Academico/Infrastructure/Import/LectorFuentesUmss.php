<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Import;

use App\Modules\Academico\Application\Contracts\FuenteCalendarioGateway;
use App\Modules\Academico\Application\DTOs\CalendarioData;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;

/**
 * Lee de `database/umss/fuentes.json` los calendarios academicos y las
 * fechas que ya se leyeron de cada uno (`fechas_leidas`).
 */
final class LectorFuentesUmss implements FuenteCalendarioGateway
{
    /**
     * @return list<CalendarioData>
     */
    public function calendarios(): array
    {
        $ruta = config('umss.ruta_fuentes');

        try {
            $datos = LectorJson::leer(is_string($ruta) ? $ruta : database_path('umss/fuentes.json'));
        } catch (FuenteNoDisponibleException) {
            return [];
        }

        $lista = $datos['calendarios_academicos'] ?? null;
        $calendarios = [];

        foreach (is_array($lista) ? $lista : [] as $calendario) {
            if (! is_array($calendario)) {
                continue;
            }

            $periodo = $calendario['periodo'] ?? null;

            if (! is_string($periodo) || preg_match('/^\d\/\d{4}$/', $periodo) !== 1) {
                continue;
            }

            $fechas = $calendario['fechas_leidas'] ?? null;
            $fechas = is_array($fechas) ? $fechas : [];
            $ventanas = [];

            foreach ($fechas as $nombre => $rango) {
                if (is_string($nombre) && is_array($rango)) {
                    $ventanas[$nombre] = $rango;
                }
            }

            $facultad = $calendario['facultad'] ?? null;

            $calendarios[] = new CalendarioData(
                periodo: $periodo,
                inicio: $this->fecha($fechas['inicio'] ?? null),
                fin: $this->fecha($fechas['fin'] ?? null),
                ventanas: $ventanas,
                fuente: 'Calendario académico'.(is_string($facultad) ? ' '.$facultad : '').' '.$periodo,
            );
        }

        return $calendarios;
    }

    private function fecha(mixed $valor): ?string
    {
        if (! is_string($valor) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes) !== 1) {
            return null;
        }

        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]) ? $valor : null;
    }
}
