<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Domain\Rules;

/**
 * El reparto por aula: cada estudiante, en el orden recibido, va al aula
 * con menos asignados en ese momento. En empate gana la primera segun el
 * orden en que llegan las aulas. No hay tope: las aulas no tienen
 * capacidad.
 */
final class RepartoEquilibrado
{
    /**
     * @param  array<int, int>  $cargaPorAula  aula => asignados, en el orden de desempate
     * @param  list<int>  $pendientes  a quienes hay que ubicar, ya ordenados
     * @return array<int, list<int>> aula => los que recibe (solo las aulas que reciben)
     */
    public function repartir(array $cargaPorAula, array $pendientes): array
    {
        $reparto = [];
        $primera = array_key_first($cargaPorAula);

        if ($primera === null) {
            return $reparto;
        }

        foreach ($pendientes as $pendiente) {
            $destino = $primera;

            foreach ($cargaPorAula as $aulaId => $carga) {
                if ($carga < $cargaPorAula[$destino]) {
                    $destino = $aulaId;
                }
            }

            $cargaPorAula[$destino]++;
            $reparto[$destino][] = $pendiente;
        }

        return $reparto;
    }
}
