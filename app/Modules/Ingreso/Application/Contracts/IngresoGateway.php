<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface IngresoGateway
{
    public function yaIngreso(int $examenId, int $estudianteId): bool;

    /**
     * Devuelve false cuando ya existe un ingreso para ese estudiante
     * y examen. Los demás errores de base de datos se propagan.
     *
     * Solo debe invocarse después de validar las reglas de admisión.
     */
    public function registrar(
        int $examenId,
        int $estudianteId,
        int $aulaId,
        ?int $usuarioId = null,
        ?int $accesoCodigoAulaId = null,
        ?string $medioIdentificacion = null,
    ): bool;
}
