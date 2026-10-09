<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\PlantillaNormaData;

/**
 * Las plantillas de normas: las predefinidas del sistema y las propias de
 * cada cuenta.
 */
interface PlantillaNormaGateway
{
    /**
     * Las predefinidas y despues las de la cuenta; nunca las de otros.
     *
     * @return list<PlantillaNormaData>
     */
    public function visiblesPara(int $usuarioId): array;

    /**
     * Sin comprobar de quien es.
     */
    public function buscar(int $plantillaId): ?PlantillaNormaData;

    /**
     * Guarda la plantilla de la cuenta y asienta `norma.plantilla_crear`.
     */
    public function crear(int $usuarioId, string $texto): PlantillaNormaData;

    /**
     * Cambia el texto y asienta `norma.plantilla_editar`. No toca las
     * normas ya guardadas en examenes.
     */
    public function editar(int $plantillaId, string $texto, int $usuarioId): ?PlantillaNormaData;

    /**
     * Quita la plantilla y asienta `norma.plantilla_quitar`. Los examenes
     * que la tenian marcada conservan el texto.
     */
    public function quitar(int $plantillaId, int $usuarioId): bool;
}
