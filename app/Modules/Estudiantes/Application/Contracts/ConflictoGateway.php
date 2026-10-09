<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

use App\Modules\Estudiantes\Application\DTOs\ConflictoData;
use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;
use App\Modules\Estudiantes\Domain\Enums\ResolucionConflicto;
use App\Modules\Estudiantes\Domain\Exceptions\ConflictoYaResueltoException;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;

/**
 * Las diferencias entre una carga y el padron, que resuelve la
 * administracion.
 */
interface ConflictoGateway
{
    /**
     * Los conflictos en el estado pedido (`PENDIENTE`, `RESUELTO` o null =
     * todos), del mas antiguo al mas nuevo.
     */
    public function listar(FiltroListaData $filtro): PaginaData;

    public function buscar(int $conflictoId): ?ConflictoData;

    /**
     * Cierra el conflicto y crea la inscripcion que estaba en espera; con
     * `USAR_CARGA` ademas reemplaza documento, nombres y apellidos y marca
     * al estudiante como verificado. Todo en una transaccion, con su
     * asiento de bitacora.
     *
     * @throws ConflictoYaResueltoException
     * @throws DocumentoEnUsoException
     */
    public function resolver(int $conflictoId, ResolucionConflicto $resolucion, int $usuarioId): void;
}
