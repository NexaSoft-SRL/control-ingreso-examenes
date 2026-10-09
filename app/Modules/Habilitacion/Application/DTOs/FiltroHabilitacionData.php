<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * Los filtros de la lista de habilitacion. Sirven igual para mostrarla
 * que para «Seleccionar los N» al cambiar la condicion en lote.
 */
final readonly class FiltroHabilitacionData
{
    public const HABILITADO = 'habilitado';

    public const NO_HABILITADO = 'no';

    public const PENDIENTE = 'pendiente';

    /**
     * @var list<string>
     */
    public const CONDICIONES = [
        self::HABILITADO,
        self::NO_HABILITADO,
        self::PENDIENTE,
    ];

    public function __construct(
        public ?int $grupoId = null,
        public ?int $aulaId = null,
        public ?string $condicion = null,
        public ?string $buscar = null,
    ) {}

    public function sinCondicion(): self
    {
        return new self($this->grupoId, $this->aulaId, null, $this->buscar);
    }
}
