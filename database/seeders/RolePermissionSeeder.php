<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\Permission;

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

        $admin = Role::where('name', 'Administrador')->first();
        
        if ($admin instanceof Role) {
            $permissionIds = Permission::whereIn('name', ['padron_estudiantes', 'asignaturas_ambientes', 'codigos_qr', 'usuarios_roles', 'bitacora', 'respaldo_restauracion'])->pluck('id')->toArray();
            /** @phpstan-ignore-next-line */
            $admin->permissions()->sync($permissionIds);
        }
    }
}