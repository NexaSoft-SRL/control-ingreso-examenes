<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Rules;

/**
 * El reparto de fabrica de los tres roles de inicio: lo usan la carga
 * inicial y «Restablecer roles base», que devuelve a estos tres su
 * reparto sin tocar los roles creados despues.
 */
final class PermisosDeInicio
{
    /**
     * @var array<string, list<string>>
     */
    public const POR_ROL = [
        // Prepara los datos y administra el sistema. Las pantallas de
        // docencia no le sirven: muestran solo lo propio de quien entra.
        // Desde la matriz se le pueden marcar.
        'Administrador' => [
            'periodo_oferta',
            'aulas_docentes',
            'padron_estudiantes',
            'reportes_universidad',
            'usuarios_roles',
            'bitacora',
            'respaldo_restauracion',
        ],
        'Docente' => [
            'mis_grupos',
            'examenes',
            'habilitacion',
            'codigos_qr',
            'punto_control',
            'seguimiento_vivo',
            'reportes_examenes',
        ],
        'Auxiliar' => [
            'punto_control',
            'seguimiento_vivo',
        ],
    ];
}
