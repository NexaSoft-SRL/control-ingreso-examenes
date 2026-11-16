<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Lo que la revision de una sesion sin conexion encuentra raro y el docente
 * debe resolver a mano.
 */
enum TipoIrregularidad: string
{
    case IngresoDoble = 'INGRESO_DOBLE';
    case AdmitidoNoHabilitado = 'ADMITIDO_NO_HABILITADO';
    case NoInscritoAdmitido = 'NO_INSCRITO_ADMITIDO';
    case RechazadoHabilitado = 'RECHAZADO_HABILITADO';
    case HoraDudosa = 'HORA_DUDOSA';
    case EventoInvalido = 'EVENTO_INVALIDO';
    case DispositivoSinSincronizar = 'DISPOSITIVO_SIN_SINCRONIZAR';

    public function etiqueta(): string
    {
        return match ($this) {
            self::IngresoDoble => 'Ingreso doble',
            self::AdmitidoNoHabilitado => 'Admitido sin estar habilitado',
            self::NoInscritoAdmitido => 'Admitido sin estar inscrito',
            self::RechazadoHabilitado => 'Rechazado estando habilitado',
            self::HoraDudosa => 'Hora dudosa',
            self::EventoInvalido => 'Evento no válido',
            self::DispositivoSinSincronizar => 'Dispositivo sin sincronizar',
        };
    }

    /**
     * @return 'ALTA'|'MEDIA'|'BAJA'
     */
    public function gravedad(): string
    {
        return match ($this) {
            self::IngresoDoble, self::AdmitidoNoHabilitado, self::NoInscritoAdmitido, self::DispositivoSinSincronizar => 'ALTA',
            self::RechazadoHabilitado, self::EventoInvalido => 'MEDIA',
            self::HoraDudosa => 'BAJA',
        };
    }
}
