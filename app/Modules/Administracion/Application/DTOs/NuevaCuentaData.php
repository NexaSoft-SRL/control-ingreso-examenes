<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Datos para dar de alta una cuenta. El acceso es por `usuario`; el correo
 * es un dato de contacto opcional. `rol` es el nombre de uno de los tres
 * roles del sistema.
 */
final readonly class NuevaCuentaData
{
    public const ROL_ADMINISTRADOR = 'Administrador';

    public const ROL_DOCENTE = 'Docente';

    public const ROL_AUXILIAR = 'Auxiliar';

    /**
     * @var list<string>
     */
    public const ROLES = [
        self::ROL_ADMINISTRADOR,
        self::ROL_DOCENTE,
        self::ROL_AUXILIAR,
    ];

    public function __construct(
        public string $nombre,
        public string $usuario,
        public ?string $correo,
        public string $rol,
    ) {}
}
