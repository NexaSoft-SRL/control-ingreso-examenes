<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface CodigoQrGateway
{
    public function generar(int $examenId, string $codigo): string;

    public function resolver(string $qr, int $examenId): string;
}
