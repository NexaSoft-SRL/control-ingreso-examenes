<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Domain\Exceptions\DatoDeCuentaEnUsoException;
use App\Modules\Academico\Domain\Exceptions\DocenteYaTieneCuentaException;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;

/**
 * La union entre un docente de la oferta y su cuenta de acceso.
 */
interface CuentaDocenteGateway
{
    /**
     * @return array{id: int, nombre: string, tiene_cuenta: bool}|null
     */
    public function docente(int $docenteId): ?array;

    /**
     * Crea la cuenta, la une al docente y asienta `docente.activar_cuenta`,
     * todo en una transaccion.
     *
     * @throws DocenteYaTieneCuentaException
     * @throws DatoDeCuentaEnUsoException
     */
    public function activar(int $docenteId, NuevaCuentaData $cuenta, ?int $autorId): CuentaCreadaData;
}
