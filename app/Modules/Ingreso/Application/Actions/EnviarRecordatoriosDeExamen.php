<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\CorreoQrGateway;

final readonly class EnviarRecordatoriosDeExamen
{
    public function __construct(
        private CodigoQrGateway $codigos,
        private CorreoQrGateway $correo,
    ) {}

    public function execute(): int
    {
        $zona = config('umss.zona_horaria');
        $zona = is_string($zona) ? $zona : 'America/La_Paz';
        $fecha = now($zona)->addDay()->toDateString();
        $total = 0;

        foreach ($this->codigos->examenesParaRecordatorio($fecha) as $examenId) {
            $destinatarios = $this->codigos->destinatariosParaRecordatorio($examenId);

            if ($destinatarios !== []) {
                $total += $this->correo->recordar($examenId, $destinatarios);
            }
        }

        return $total;
    }
}
