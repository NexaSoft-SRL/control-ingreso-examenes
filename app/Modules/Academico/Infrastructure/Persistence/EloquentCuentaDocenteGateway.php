<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\CuentaDocenteGateway;
use App\Modules\Academico\Domain\Exceptions\DatoDeCuentaEnUsoException;
use App\Modules\Academico\Domain\Exceptions\DocenteYaTieneCuentaException;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\CuentaUsuarioGateway;
use App\Modules\Administracion\Application\DTOs\CuentaCreadaData;
use App\Modules\Administracion\Application\DTOs\NuevaCuentaData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class EloquentCuentaDocenteGateway implements CuentaDocenteGateway
{
    public function __construct(
        private CuentaUsuarioGateway $cuentas,
        private BitacoraGateway $bitacora,
    ) {}

    public function docente(int $docenteId): ?array
    {
        $docente = Docente::query()->find($docenteId);

        if (! $docente instanceof Docente) {
            return null;
        }

        return [
            'id' => $docente->id,
            'nombre' => $docente->nombre_completo,
            'tiene_cuenta' => $docente->tieneCuenta(),
        ];
    }

    public function activar(int $docenteId, NuevaCuentaData $cuenta, ?int $autorId): CuentaCreadaData
    {
        try {
            return DB::transaction(function () use ($docenteId, $cuenta, $autorId): CuentaCreadaData {
                // El bloqueo de la fila evita que dos activaciones a la vez
                // dejen dos cuentas para el mismo docente.
                $docente = Docente::query()->lockForUpdate()->find($docenteId);

                if (! $docente instanceof Docente || $docente->tieneCuenta()) {
                    throw new DocenteYaTieneCuentaException;
                }

                $creada = $this->cuentas->crear($cuenta, $autorId);

                $docente->forceFill(['user_id' => $creada->id])->save();

                $this->bitacora->registrar(
                    $autorId,
                    'docente.activar_cuenta',
                    'docentes',
                    $docente->id,
                    "Cuenta «{$creada->usuario}» activada para {$docente->nombre_completo}",
                );

                return $creada;
            }, 3);
        } catch (QueryException) {
            // Otra peticion tomo el usuario entre la comprobacion y el alta.
            throw DatoDeCuentaEnUsoException::usuario();
        }
    }
}
