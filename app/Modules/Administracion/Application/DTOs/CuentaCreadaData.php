<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Resultado de emitir una contrasena temporal, al crear la cuenta o al
 * restablecerla. La contrasena en claro existe solo aqui: se muestra una
 * vez a quien la pidio y no se guarda ni se asienta en la bitacora.
 */
final readonly class CuentaCreadaData
{
    public function __construct(
        public int $id,
        public string $usuario,
        public string $contrasenaTemporal,
        // Correo al que ademas se envio, o null si la cuenta no tiene.
        public ?string $enviadaA,
        // Instante ISO 8601 con zona en que la temporal deja de servir.
        public string $caducaEn,
    ) {}
}
