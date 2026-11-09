<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\AuxiliarData;

interface AuxiliarGateway
{
    /**
     * Sin `$buscar`, los auxiliares que el docente registro o ya puso en
     * un aula suya. Con `$buscar`, cualquier cuenta activa de rol Auxiliar
     * (los suyos primero, veinte como mucho).
     *
     * @return list<AuxiliarData>
     */
    public function listar(int $usuarioId, ?string $buscar): array;

    public function porCodigo(string $codigoUniversitario, int $usuarioId): ?AuxiliarData;

    /**
     * Guarda de quien es la cuenta y su codigo, y asienta
     * `auxiliar.registrar`.
     */
    public function registrar(int $cuentaId, string $codigoUniversitario, int $registradoPor): void;

    /**
     * Si la cuenta atiende, a cargo o de apoyo, un aula de un examen que
     * registro `$usuarioId` y que todavia no termino.
     */
    public function atiendeExamenPendiente(int $cuentaId, int $usuarioId): bool;

    /**
     * Saca al auxiliar de la lista de quien lo dio de alta (la cuenta y sus
     * registros quedan) y asienta `auxiliar.quitar`. Falso si `$usuarioId`
     * no lo habia registrado.
     */
    public function quitar(int $cuentaId, int $usuarioId): bool;
}
