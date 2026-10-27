<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Rules\PermisosDeInicio;
use Illuminate\Database\Seeder;

/**
 * Los tres roles de inicio y el permiso de cada pantalla. Se puede correr
 * mas de una vez: no duplica y deja cada rol de inicio con su reparto
 * exacto. Los roles creados desde la pantalla no se tocan.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Clave del permiso => pantalla, en el orden de la matriz.
     *
     * @var array<string, string>
     */
    public const PERMISOS = [
        'periodo_oferta' => 'Período y oferta académica',
        'aulas_docentes' => 'Aulas y docentes',
        'padron_estudiantes' => 'Padrón e inscripciones',
        'mis_grupos' => 'Padrón e inscripciones (sus grupos)',
        'examenes' => 'Exámenes',
        'habilitacion' => 'Habilitación',
        'codigos_qr' => 'Códigos QR',
        'punto_control' => 'Punto de control',
        'seguimiento_vivo' => 'Seguimiento en vivo',
        'reportes_universidad' => 'Reportes de la universidad',
        'reportes_examenes' => 'Reportes de sus exámenes',
        'usuarios_roles' => 'Usuarios y roles',
        'bitacora' => 'Bitácora',
        'respaldo_restauracion' => 'Respaldo',
    ];

    /**
     * El reparto de fabrica vive en el dominio: «Restablecer roles base»
     * usa el mismo.
     *
     * @var array<string, list<string>>
     */
    public const POR_ROL = PermisosDeInicio::POR_ROL;

    public function run(): void
    {
        foreach (array_keys(self::POR_ROL) as $nombreRol) {
            Role::updateOrCreate(
                ['name' => $nombreRol],
                ['es_sistema' => true],
            );
        }

        foreach (self::PERMISOS as $clave => $pantalla) {
            Permission::updateOrCreate(
                ['name' => $clave],
                ['screen_name' => $pantalla],
            );
        }

        foreach (self::POR_ROL as $nombreRol => $permisos) {
            $rol = Role::where('name', $nombreRol)->first();

            if (! $rol instanceof Role) {
                continue;
            }

            /** @var list<int> $identificadores */
            $identificadores = Permission::whereIn('name', $permisos)
                ->pluck('id')
                ->all();

            $rol->permissions()->sync($identificadores);
        }
    }
}
