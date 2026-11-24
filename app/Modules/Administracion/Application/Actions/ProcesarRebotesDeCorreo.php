<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\BuzonDeRebotes;
use App\Modules\Administracion\Application\Contracts\RegistroCorreoGateway;

/**
 * Un correo a una direccion que no existe sale bien del sistema: el servidor
 * de correo lo acepta y despues avisa del rebote en el buzon del remitente.
 * Aqui se leen esos avisos y los envios afectados pasan a fallidos, para que
 * quien envio (el docente con los codigos QR, la administracion con las
 * credenciales) vea que el correo no llego.
 */
final readonly class ProcesarRebotesDeCorreo
{
    public const ERROR = 'La dirección no existe (el correo rebotó)';

    // Un rebote se atribuye a los envios de los dias anteriores.
    public const DIAS = 7;

    public function __construct(
        private BuzonDeRebotes $buzon,
        private RegistroCorreoGateway $registro,
        private BitacoraGateway $bitacora,
        // Sin buzon configurado (local, pruebas) no se lee nada.
        private bool $habilitado = false,
    ) {}

    /**
     * Devuelve cuantos envios quedaron como fallidos.
     */
    public function execute(): int
    {
        if (! $this->habilitado) {
            return 0;
        }

        $rebotes = $this->buzon->rebotes();

        if ($rebotes === []) {
            return 0;
        }

        $porDestinatario = [];
        $mensajes = [];

        foreach ($rebotes as $rebote) {
            $marcados = $this->registro->marcarRebotados(
                $rebote->destinatario,
                $rebote->fecha->modify(sprintf('-%d days', self::DIAS)),
                $rebote->fecha,
                self::ERROR,
            );

            $porDestinatario[$rebote->destinatario] = ($porDestinatario[$rebote->destinatario] ?? 0) + $marcados;
            $mensajes[$rebote->mensajeId] = true;
        }

        $total = 0;

        foreach ($porDestinatario as $destinatario => $marcados) {
            if ($marcados === 0) {
                continue;
            }

            $total += $marcados;

            $this->bitacora->registrar(
                null,
                'correo.rebote',
                'envios_correo',
                null,
                sprintf(
                    '%s · %s',
                    $destinatario,
                    $marcados === 1 ? '1 correo no llegó' : "{$marcados} correos no llegaron",
                ),
            );
        }

        $this->buzon->archivar(array_map('strval', array_keys($mensajes)));

        return $total;
    }
}
