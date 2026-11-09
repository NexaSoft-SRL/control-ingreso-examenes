<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\QR;

use App\Modules\Estudiantes\Application\Contracts\CodigosQrDelEstudiante;
use App\Modules\Estudiantes\Application\DTOs\ImagenQrData;
use App\Modules\Ingreso\Application\Contracts\EmisionQrGateway;
use App\Modules\Ingreso\Application\Contracts\GeneradorImagenQr;

/**
 * El mismo QR que va en el correo, para el portal del estudiante: solo el
 * de un habilitado y ya emitido. Se descarga como `qr-<codigo>-<fecha>.png`.
 */
final readonly class CodigosQrDelEstudianteEmitidos implements CodigosQrDelEstudiante
{
    public function __construct(
        private EmisionQrGateway $emision,
        private GeneradorImagenQr $imagenes,
    ) {}

    public function png(int $examenId, int $estudianteId): ?ImagenQrData
    {
        $credencial = $this->emision->credencial($examenId, $estudianteId);

        if ($credencial === null || $credencial->contenido === null) {
            return null;
        }

        return new ImagenQrData(
            nombre: sprintf('qr-%s-%s.png', $credencial->codigo, substr($credencial->fecha, 0, 10)),
            png: $this->imagenes->comoPng($credencial->contenido),
        );
    }
}
