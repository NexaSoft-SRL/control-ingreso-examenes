<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\AlmacenImagenes;
use App\Modules\Estudiantes\Application\Contracts\FotosDeEstudiantes;
use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;

final readonly class FotosDeEstudiantesEnDisco implements FotosDeEstudiantes
{
    public function __construct(
        private PortalEstudianteGateway $portal,
        private AlmacenImagenes $imagenes,
    ) {}

    public function foto(int $estudianteId): ?string
    {
        $ruta = $this->portal->rutaFoto($estudianteId);

        return $ruta === null ? null : $this->imagenes->leer($ruta);
    }
}
