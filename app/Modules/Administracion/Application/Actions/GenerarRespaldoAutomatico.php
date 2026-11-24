<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\RespaldoGateway;
use DateTimeImmutable;
use DateTimeZone;

/**
 * El respaldo de cada noche para un servidor sin tareas programadas: se
 * genera con la misma accion del planificador, sin usuario (queda como
 * programado y la bitacora lo asienta como del sistema), la primera vez que
 * alguien usa el sistema despues de la hora de corte, y una sola vez al dia.
 *
 * Si el planificador tambien corre, no se duplica: su respaldo de las 03:00
 * ya es posterior al corte del dia.
 */
final readonly class GenerarRespaldoAutomatico
{
    public const HORA_POR_DEFECTO = '03:00';

    public function __construct(
        private RespaldoGateway $respaldos,
        private GenerarRespaldo $generar,
        // «HH:MM» en la zona de la universidad.
        private string $hora = self::HORA_POR_DEFECTO,
        private string $zona = 'America/La_Paz',
    ) {}

    /**
     * Toca respaldar si ya paso el corte de hoy y ningun respaldo programado
     * correcto es posterior a ese corte.
     */
    public function corresponde(DateTimeImmutable $ahora): bool
    {
        $corte = $this->corteDe($ahora);

        if ($ahora < $corte) {
            return false;
        }

        $ultimo = $this->respaldos->ultimoProgramadoCorrectoEn();

        return $ultimo === null || new DateTimeImmutable($ultimo) < $corte;
    }

    /**
     * Genera el respaldo. Si falla no lanza nada: queda la fila fallida en el
     * historial, con su error.
     */
    public function execute(): void
    {
        $this->generar->execute();
    }

    private function corteDe(DateTimeImmutable $ahora): DateTimeImmutable
    {
        $local = $ahora->setTimezone(new DateTimeZone($this->zona));

        $partes = explode(':', preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->hora) === 1
            ? $this->hora
            : self::HORA_POR_DEFECTO);

        return $local->setTime((int) $partes[0], (int) $partes[1]);
    }
}
