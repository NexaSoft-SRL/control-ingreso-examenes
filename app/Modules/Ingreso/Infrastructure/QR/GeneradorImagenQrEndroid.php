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
 * Dibuja el QR en el servidor (endroid/qr-code, PNG con GD). El contenido
 * no sale del sistema.
 */
final class GeneradorImagenQrEndroid implements GeneradorImagenQr
{
    public function comoUriDeDatos(string $contenido): string
    {
        return $this->dibujar($contenido)->getDataUri();
    }

    public function comoPng(string $contenido): string
    {
        return $this->dibujar($contenido)->getString();
    }

    private function dibujar(string $contenido): ResultInterface
    {
        $constructor = new Builder(
            writer: new PngWriter,
            data: $contenido,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 8,
        );

        return $constructor->build();
    }
}
