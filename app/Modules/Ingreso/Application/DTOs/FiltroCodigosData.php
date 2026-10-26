<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Los filtros de la lista de codigos QR: los mismos sirven para la lista,
 * el envio por correo, los avisos y la hoja imprimible. `envio` filtra por
 * el estado del codigo: `sin_emitir`, `sin_enviar`, `enviado` o `fallido`.
 */
final readonly class FiltroCodigosData
{
    public const ENVIOS = ['sin_emitir', 'sin_enviar', 'enviado', 'fallido'];

    public function __construct(
        public ?int $grupo = null,
        public ?int $aula = null,
        public ?string $buscar = null,
        public int $pagina = 1,
        public int $porPagina = 20,
        public ?string $envio = null,
    ) {}
}
