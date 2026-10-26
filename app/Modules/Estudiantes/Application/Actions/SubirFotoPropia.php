<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Domain\Exceptions\FotoYaSubidaException;
use App\Modules\Estudiantes\Domain\Exceptions\FueraDePlazoDeFotoException;
use App\Modules\Estudiantes\Domain\Exceptions\ImagenNoValidaException;

/**
 * El estudiante sube su foto una sola vez y dentro del plazo: asi un
 * suplantador no puede cambiarla el dia del examen. Fuera del plazo, o
 * para cambiarla, necesita que un docente de sus grupos se lo habilite.
 * El sistema la reduce.
 */
final readonly class SubirFotoPropia
{
    public const LADO = 480;

    public function __construct(
        private PortalEstudianteGateway $portal,
        private AlmacenImagenes $imagenes,
        private BitacoraGateway $bitacora,
    ) {}

    public function execute(int $estudianteId, string $imagen, string $hoy): void
    {
        $estado = $this->portal->estadoFoto($estudianteId);
        $habilitado = $estado['habilitada_hasta'] !== null && $estado['habilitada_hasta'] >= $hoy;

        if (! $habilitado) {
            if ($this->portal->rutaFoto($estudianteId) !== null) {
                throw new FotoYaSubidaException;
            }

            $plazo = $this->portal->plazo();

            if ($plazo === null || ! $plazo->abiertoEl($hoy)) {
                throw new FueraDePlazoDeFotoException;
            }
        }

        $ruta = $this->imagenes->guardar('estudiantes', bin2hex(random_bytes(16)), $imagen, self::LADO);

        if ($ruta === null) {
            throw new ImagenNoValidaException;
        }

        $anterior = $this->portal->ponerFoto($estudianteId, $ruta);

        if ($anterior !== null) {
            $this->imagenes->borrar($anterior);
        }

        $estudiante = $this->portal->estudiante($estudianteId);
        $this->bitacora->registrar(
            null,
            'estudiante.foto',
            'estudiantes',
            $estudianteId,
            $estudiante === null
                ? 'Foto subida por el propio estudiante desde su acceso.'
                : sprintf(
                    'Foto de %s · %s, %s desde su acceso%s.',
                    $estudiante->nombre,
                    $estudiante->codigo,
                    $anterior === null ? 'subida' : 'cambiada',
                    $habilitado ? ' con el cambio que habilitó '.($estado['habilitada_por'] ?? 'un docente') : '',
                ),
        );
    }
}
