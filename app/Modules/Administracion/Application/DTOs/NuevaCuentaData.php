<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Datos para dar de alta una cuenta. El acceso es por correo: una cuenta
 * sin correo existe, pero no puede entrar. `rol` es el nombre de un rol
 * existente, de inicio o creado.
 */
final readonly class NuevaCuentaData
{
    public const ROL_ADMINISTRADOR = 'Administrador';

    public const ROL_DOCENTE = 'Docente';

    public const ROL_AUXILIAR = 'Auxiliar';

    public function __construct(
        public string $nombre,
        public string $usuario,
        public ?string $correo,
        public string $rol,
    ) {}
}
