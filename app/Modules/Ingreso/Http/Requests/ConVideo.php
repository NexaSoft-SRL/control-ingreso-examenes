<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Http\Requests;

use App\Modules\Ingreso\Application\DTOs\VideoData;
use Illuminate\Http\UploadedFile;

/**
 * El video de evidencia de una incidencia: uno, de hasta 8 MB (el limite de
 * subida del servidor). La duracion (60 segundos) la cuida el navegador.
 */
trait ConVideo
{
    /**
     * Los tipos que se aceptan; el contenido decide, no la extension.
     */
    private const TIPOS_VIDEO = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska'];

    /**
     * @return array<string, list<string>>
     */
    protected function reglasDeVideo(): array
    {
        return [
            'video' => ['nullable', 'file', 'mimetypes:'.implode(',', self::TIPOS_VIDEO), 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesDeVideo(): array
    {
        return [
            'video.uploaded' => 'Máximo 8 MB',
            'video.max' => 'Máximo 8 MB',
            'video.*' => 'Solo MP4, WebM o MOV',
        ];
    }

    public function video(): ?VideoData
    {
        $archivo = $this->file('video');

        if (! $archivo instanceof UploadedFile) {
            return null;
        }

        $tipo = (string) $archivo->getMimeType();

        // Lo que graba Chrome se reconoce a veces como Matroska: es WebM.
        return new VideoData($archivo->getContent(), $tipo === 'video/x-matroska' ? 'video/webm' : $tipo);
    }
}
