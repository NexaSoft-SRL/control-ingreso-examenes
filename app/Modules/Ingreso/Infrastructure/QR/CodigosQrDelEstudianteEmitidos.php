<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\QR;

use App\Modules\Estudiantes\Application\Contracts\CodigosQrDelEstudiante;
use App\Modules\Estudiantes\Application\DTOs\ImagenQrData;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;

/**
 * El mismo QR que va en el correo, para el portal del estudiante: solo el
 * de un habilitado y ya emitido.
 */
final readonly class CodigosQrDelEstudianteEmitidos implements CodigosQrDelEstudiante
{
    public function __construct(
        private CodigoQrGateway $codigos,
        private GeneradorImagenQr $imagenes,
    ) {}

    public function png(int $examenId, int $estudianteId): ?ImagenQrData
    {
        $credencial = $this->codigos->credencial($examenId, $estudianteId);

        if ($credencial === null || $credencial->token === null) {
            return null;
        }

        return new ImagenQrData(
            nombre: $credencial->nombreDelQr(),
            png: $this->imagenes->comoPng($credencial->token),
        );
    }
}
