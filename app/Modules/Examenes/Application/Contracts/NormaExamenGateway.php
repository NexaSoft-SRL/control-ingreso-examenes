<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\RegistrarNormaExamenData;

interface NormaExamenGateway
{
    /**
     * @return list<array<string, mixed>>|null Null when the exam does not exist.
     */
    public function listar(int $examenId): ?array;

    /**
     * @return list<array<string, mixed>>|null Null when exam or student does not exist.
     */
    public function listarParaEstudiante(int $examenId, int $estudianteId): ?array;

    /**
     * @return array<string, mixed>|null Null when the exam does not exist.
     */
    public function registrar(
        int $examenId,
        RegistrarNormaExamenData $data,
        int $usuarioId,
    ): ?array;

    /**
     * @return bool|null False when the rule is not part of the exam; null when the exam does not exist.
     */
    public function eliminar(
        int $examenId,
        int $normaId,
        int $usuarioId,
    ): ?bool;
}
