<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

/**
 * Como se hace el respaldo automatico: si esta activo, a que hora (HH:MM, en
 * la zona de la universidad) y cuantos minutos se espera para reintentar.
 */
final readonly class AjustesDeRespaldoData
{
    public const REINTENTO_MINIMO = 5;

    public const REINTENTO_MAXIMO = 720;

    public function __construct(
        public bool $habilitado,
        public string $hora,
        public int $reintentoMinutos,
    ) {}

    /**
     * Lo que se muestra bajo el titulo de la pantalla.
     */
    public function programacion(): string
    {
        return $this->habilitado ? 'Diario, '.$this->hora : 'Automático apagado';
    }
}
