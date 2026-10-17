<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

interface CodigoAulaGateway
{
    public function aulaPerteneceAlExamen(int $examenId, int $aulaId): bool;

    /**
     * @return array{codigo: string|null, generado_en: string|null}
     */
    public function consultar(int $examenId, int $aulaId): array;

    /**
     * @return array{codigo: string, generado_en: string, regenerado: bool}
     */
    public function generar(int $examenId, int $aulaId, int $usuarioId): array;
}
