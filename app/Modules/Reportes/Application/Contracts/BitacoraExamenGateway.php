<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

use App\Modules\Reportes\Application\DTOs\ControlDeAula;
use App\Modules\Reportes\Application\DTOs\SucesoExamen;

/**
 * Todo lo ocurrido en un examen, en orden: ingresos, intentos rechazados,
 * incidencias (con su atencion y su correccion), accesos con codigo de aula
 * y lo que se hizo con el examen (registro, habilitacion, codigos QR,
 * avisos, inicio y fin). Ademas, quien controlo cada aula. Solo lectura.
 */
interface BitacoraExamenGateway
{
    /**
     * @return list<SucesoExamen>
     */
    public function sucesos(int $examenId): array;

    /**
     * Las aulas del examen, por nombre, con quien las controlo.
     *
     * @return list<ControlDeAula>
     */
    public function controlDeAulas(int $examenId): array;
}
