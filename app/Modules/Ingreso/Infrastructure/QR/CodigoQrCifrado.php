<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\QR;

use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;

final class CodigoQrCifrado implements CodigoQrGateway
{
    private const PREFIJO = 'SCIQR1:';

    public function generar(int $examenId, string $codigo): string
    {
        if (
            $examenId <= 0
            || trim($codigo) === ''
            || strlen($codigo) > 20
        ) {
            throw new InvalidArgumentException('Datos QR inválidos.');
        }

        $contenido = json_encode([
            'v' => 1,
            'examen' => $examenId,
            'codigo' => $codigo,
        ], JSON_THROW_ON_ERROR);

        return self::PREFIJO.Crypt::encryptString($contenido);
    }

    public function resolver(string $qr, int $examenId): string
    {
        if (
            $examenId <= 0
            || strlen($qr) > 2048
            || ! str_starts_with($qr, self::PREFIJO)
        ) {
            throw new InvalidArgumentException('Código QR inválido.');
        }

        try {
            $contenido = Crypt::decryptString(
                substr($qr, strlen(self::PREFIJO)),
            );

            $datos = json_decode(
                $contenido,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|JsonException $error) {
            throw new InvalidArgumentException(
                'Código QR inválido.',
                previous: $error,
            );
        }

        if (
            ! is_array($datos)
            || ($datos['v'] ?? null) !== 1
            || ($datos['examen'] ?? null) !== $examenId
            || ! isset($datos['codigo'])
            || ! is_string($datos['codigo'])
            || trim($datos['codigo']) === ''
            || strlen($datos['codigo']) > 20
        ) {
            throw new InvalidArgumentException(
                'El QR no corresponde a este examen o es inválido.',
            );
        }

        return $datos['codigo'];
    }
}
