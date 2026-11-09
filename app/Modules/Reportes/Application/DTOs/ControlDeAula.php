<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Quien controlo un aula del examen: el personal asignado (el encargado
 * primero, cada uno con «docente» o «auxiliar») y quienes entraron a la
 * puerta con el codigo de aula autorizado; y cuantos ingresos registro esa
 * puerta, con la hora (`HH:MM`) del primero y del ultimo.
 */
final readonly class ControlDeAula
{
    /**
     * @param  list<array{nombre: string, rol: string}>  $personal  rol: `docente` | `auxiliar`
     * @param  list<string>  $conCodigo
     */
    public function __construct(
        public string $aula,
        public array $personal,
        public array $conCodigo,
        public int $ingresos,
        public ?string $primerIngreso,
        public ?string $ultimoIngreso,
    ) {}

    /**
     * 'Blanco Coca Leticia (docente), Rojas Ana (auxiliar), Mamani Diego (código de aula)'.
     */
    public function quienes(): string
    {
        return implode(', ', [
            ...array_map(
                static fn (array $persona): string => sprintf('%s (%s)', $persona['nombre'], $persona['rol']),
                $this->personal,
            ),
            ...array_map(static fn (string $nombre): string => "{$nombre} (código de aula)", $this->conCodigo),
        ]);
    }
}
