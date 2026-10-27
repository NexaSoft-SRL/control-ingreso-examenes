<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Contracts\IdentificacionEstudianteGateway;

/**
 * Lo escrito en el campo «Código SIS o C.I.» de la puerta, como codigo
 * universitario. Si no esta en el padron queda como documento: el intento
 * se registra sin guardar lo escrito.
 */
final readonly class ResolverIdentificador
{
    public const UNICO = 'identificador';

    public function __construct(
        private IdentificacionEstudianteGateway $identificaciones,
    ) {}

    /**
     * @return array{tipo: string, valor: string, medio: string}
     */
    public function ejecutar(string $tipo, string $identificador): array
    {
        if ($tipo !== self::UNICO) {
            return ['tipo' => $tipo, 'valor' => $identificador, 'medio' => $tipo];
        }

        $hallado = $this->identificaciones->codigoPorIdentificador($identificador);

        return $hallado === null
            ? ['tipo' => 'documento', 'valor' => mb_strtoupper(trim($identificador)), 'medio' => self::UNICO]
            : ['tipo' => 'codigo', 'valor' => $hallado['codigo'], 'medio' => $hallado['medio']];
    }
}
