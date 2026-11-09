<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\QR;

use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;

/**
 * Dibuja el codigo QR. Lo unico que codifica es el token opaco: ningun
 * dato personal viaja en la imagen.
 */
final class GeneradorImagenQrEndroid implements GeneradorImagenQr
{
    public function comoUriDeDatos(string $token): string
    {
        return $this->dibujar($token)->getDataUri();
    }

    public function comoPng(string $token): string
    {
        return $this->dibujar($token)->getString();
    }

    private function dibujar(string $token): ResultInterface
    {
        $constructor = new Builder(
            writer: new PngWriter,
            data: $token,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 220,
            margin: 8,
        );

        return $constructor->build();
    }
}
