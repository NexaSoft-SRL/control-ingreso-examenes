<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Desde HU-02 las rutas exigen el permiso del rol. Las cuentas creadas antes
 * de que existieran los roles tienen role_id nulo y quedarian sin acceso a
 * nada, asi que se les asigna Administrador: es el acceso que ya tenian de
 * hecho, porque hasta ahora ninguna ruta comprobaba permisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $administrador = DB::table('roles')->where('name', 'Administrador')->value('id');

        if ($administrador === null) {
            return;
        }

        DB::table('usuarios')
            ->whereNull('role_id')
            ->update(['role_id' => $administrador]);
    }

    public function down(): void
    {
        // No se revierte: no hay registro de que cuentas estaban sin rol.
    }
};
