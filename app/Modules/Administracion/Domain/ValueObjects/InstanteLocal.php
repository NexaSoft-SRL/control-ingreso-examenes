<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * La aplicacion, la sesion de la base y los instantes guardados comparten
 * una sola zona (`config('umss.zona_horaria')`, la de Cochabamba). Aqui se
 * les da formato para las pantallas, en un solo lugar del modulo.
 */
final readonly class InstanteLocal
{
    /**
     * @var list<string>
     */
    private const MESES = [
        'ene', 'feb', 'mar', 'abr', 'may', 'jun',
        'jul', 'ago', 'sep', 'oct', 'nov', 'dic',
    ];

    private function __construct(
        private DateTimeImmutable $momento,
    ) {}

    /**
     * Un instante como lo entrega la base: texto sin zona, en la hora de
     * la aplicacion.
     */
    public static function de(DateTimeInterface|string $valor): self
    {
        $zona = new DateTimeZone(date_default_timezone_get());

        $momento = is_string($valor)
            ? new DateTimeImmutable($valor, $zona)
            : DateTimeImmutable::createFromInterface($valor);

        return new self($momento->setTimezone($zona));
    }

    /**
     * Primer instante de un dia (`AAAA-MM-DD`), listo para comparar contra
     * una columna.
     */
    public static function inicioDelDia(string $fecha, int $diasDespues = 0): string
    {
        $inicio = new DateTimeImmutable($fecha.' 00:00:00', new DateTimeZone(date_default_timezone_get()));

        if ($diasDespues !== 0) {
            $inicio = $inicio->modify(sprintf('%+d day', $diasDespues));
        }

        return $inicio->format('Y-m-d H:i:s');
    }

    /**
     * ISO 8601 con zona: `2026-10-12T03:00:00-04:00`.
     */
    public function iso(): string
    {
        return $this->momento->format(DATE_ATOM);
    }

    /**
     * `AAAA-MM-DD` del dia local.
     */
    public function fecha(): string
    {
        return $this->momento->format('Y-m-d');
    }

    /**
     * Sello para el nombre de un archivo: `AAAAMMDD-HHMMSS`.
     */
    public function sello(int $segundosDespues = 0): string
    {
        return $this->momento
            ->modify(sprintf('%+d second', $segundosDespues))
            ->format('Ymd-His');
    }

    /**
     * Como se lee en un aviso: `12 oct 2026, 03:00`.
     */
    public function corto(): string
    {
        return sprintf(
            '%d %s %s, %s',
            (int) $this->momento->format('j'),
            self::MESES[(int) $this->momento->format('n') - 1],
            $this->momento->format('Y'),
            $this->momento->format('H:i'),
        );
    }
}
