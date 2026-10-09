<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Apoyo para las pruebas: cada pantalla exige un permiso, asi que un
 * usuario de prueba necesita un rol que lo tenga. La cuenta sale de la
 * fabrica: con `usuario` unico, contrasena `password` ya definida (no
 * temporal) y activa.
 */
trait UsuarioConPermisos
{
    /**
     * @param  list<string>  $permisos
     */
    protected function usuarioConPermisos(array $permisos): User
    {
        $usuario = UserFactory::new()->createOne();

        $rol = Role::firstOrCreate(
            ['name' => 'Rol de prueba'],
            ['es_sistema' => false],
        );

        foreach ($permisos as $permiso) {
            $registro = Permission::firstOrCreate(
                ['name' => $permiso],
                ['screen_name' => $permiso],
            );

            DB::table('permission_role')->updateOrInsert([
                'role_id' => $rol->id,
                'permission_id' => $registro->id,
            ]);
        }

        $usuario->forceFill(['role_id' => $rol->id])->save();

        return $usuario;
    }

    /**
     * Cuenta con uno de los tres roles de inicio (`Administrador`,
     * `Docente`, `Auxiliar`) y su reparto real de permisos, el que deja
     * `RolePermissionSeeder`.
     *
     * @param  array<string, mixed>  $atributos
     */
    protected function usuarioConRol(string $rol, array $atributos = []): User
    {
        if (! Role::where('name', $rol)->where('es_sistema', true)->exists()) {
            (new RolePermissionSeeder)->run();
        }

        $usuario = UserFactory::new()->createOne($atributos);

        $usuario->forceFill([
            'role_id' => Role::where('name', $rol)->firstOrFail()->id,
        ])->save();

        return $usuario;
    }
}
