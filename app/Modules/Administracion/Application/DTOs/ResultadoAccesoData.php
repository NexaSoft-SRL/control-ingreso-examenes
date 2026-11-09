<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Desenlace de un intento de inicio de sesion.
 */
final readonly class ResultadoAccesoData
{
    public const CORRECTO = 'correcto';

    public const CREDENCIALES = 'credenciales';

    // Bloqueo temporal por intentos fallidos.
    public const BLOQUEADA = 'bloqueada';

    // Cuenta bloqueada por la administracion (is_active = false).
    public const INACTIVA = 'inactiva';

    public const TEMPORAL_VENCIDA = 'temporal_vencida';

    public function __construct(
        public string $estado,
        public ?int $usuarioId = null,
        // Solo cuando la cuenta existe y la contrasena no coincide.
        public ?int $intentosRestantes = null,
        // Solo en el bloqueo por intentos.
        public ?int $minutosBloqueo = null,
    ) {}

    public function esCorrecto(): bool
    {
        return $this->estado === self::CORRECTO && $this->usuarioId !== null;
    }
}
