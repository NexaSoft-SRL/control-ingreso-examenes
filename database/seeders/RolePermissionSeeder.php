<?php

namespace Database\Seeders;

use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Administrador', 'Docente', 'Personal', 'Responsable'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $permissions = [
            ['name' => 'padron_estudiantes', 'screen_name' => 'Padrón de estudiantes'],
            ['name' => 'asignaturas_ambientes', 'screen_name' => 'Asignaturas y ambientes'],
            ['name' => 'examenes_normas', 'screen_name' => 'Exámenes y normas'],
            ['name' => 'habilitacion', 'screen_name' => 'Habilitación'],
            ['name' => 'codigos_qr', 'screen_name' => 'Códigos QR'],
            ['name' => 'punto_control', 'screen_name' => 'Punto de control'],
            ['name' => 'monitoreo_tiempo_real', 'screen_name' => 'Monitoreo en tiempo real'],
            ['name' => 'reportes_consolidados', 'screen_name' => 'Reportes consolidados'],
            ['name' => 'reportes_asignatura', 'screen_name' => 'Reportes de mi asignatura'],
            ['name' => 'usuarios_roles', 'screen_name' => 'Usuarios y roles'],
            ['name' => 'bitacora', 'screen_name' => 'Bitácora'],
            ['name' => 'respaldo_restauracion', 'screen_name' => 'Respaldo y restauración'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate($perm);
        }

        // Cada rol opera dentro de sus atribuciones (HU-02). Este es el
        // reparto del pliego; desde la pantalla de roles se puede cambiar.
        $porRol = [
            'Administrador' => [
                'padron_estudiantes',
                'asignaturas_ambientes',
                'examenes_normas',
                'habilitacion',
                'codigos_qr',
                'punto_control',
                'monitoreo_tiempo_real',
                'reportes_consolidados',
                'reportes_asignatura',
                'usuarios_roles',
                'bitacora',
                'respaldo_restauracion',
            ],
            'Docente' => [
                'examenes_normas',
                'habilitacion',
                'reportes_asignatura',
            ],
            'Personal' => [
                'punto_control',
                'codigos_qr',
            ],
            'Responsable' => [
                'monitoreo_tiempo_real',
                'reportes_consolidados',
            ],
        ];

        foreach ($porRol as $nombre => $permisos) {
            $rol = Role::where('name', $nombre)->first();

            if (! $rol instanceof Role) {
                continue;
            }

            /** @var list<int> $ids */
            $ids = Permission::whereIn('name', $permisos)->pluck('id')->all();

            $rol->permissions()->sync($ids);
        }

    }
}
