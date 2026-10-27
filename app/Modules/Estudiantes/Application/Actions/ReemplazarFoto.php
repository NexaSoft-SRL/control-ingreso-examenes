<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Domain\Exceptions\ImagenNoValidaException;

/**
 * La administracion pone o cambia la foto de un estudiante, en cualquier
 * momento. Queda en la bitacora.
 */
final readonly class ReemplazarFoto
{
    public function __construct(
        private PortalEstudianteGateway $portal,
        private AlmacenImagenes $imagenes,
        private BitacoraGateway $bitacora,
    ) {}

    public function execute(int $estudianteId, string $imagen, int $usuarioId): void
    {
        $ruta = $this->imagenes->guardar('estudiantes', bin2hex(random_bytes(16)), $imagen, SubirFotoPropia::LADO);

        if ($ruta === null) {
            throw new ImagenNoValidaException;
        }

        $anterior = $this->portal->ponerFoto($estudianteId, $ruta);

        if ($anterior !== null) {
            $this->imagenes->borrar($anterior);
        }

        $this->bitacora->registrar(
            $usuarioId,
            'estudiante.foto.reemplazar',
            'estudiantes',
            $estudianteId,
            sprintf(
                '%s %s por la administración.',
                $this->deQuien($estudianteId),
                $anterior === null ? 'registrada' : 'reemplazada',
            ),
        );
    }

    // 'Foto de Apaza Lima Ana · 202100001,'.
    private function deQuien(int $estudianteId): string
    {
        $estudiante = $this->portal->estudiante($estudianteId);

        return $estudiante === null ? 'Foto' : sprintf('Foto de %s · %s,', $estudiante->nombre, $estudiante->codigo);
    }
}
