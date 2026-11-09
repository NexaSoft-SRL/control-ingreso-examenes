<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

use App\Modules\Estudiantes\Application\DTOs\ImagenQrData;

/**
 * El codigo QR de un estudiante para un examen, para que lo descargue desde
 * su portal. Lo implementa el modulo que emite los codigos (Ingreso).
 */
interface CodigosQrDelEstudiante
{
    /**
     * El PNG de su propio codigo: nulo si no esta habilitado en ese examen
     * o si el codigo todavia no se emitio.
     */
    public function png(int $examenId, int $estudianteId): ?ImagenQrData;
}
