<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La consultora pidio en la sexta revision que los roles y permisos no
 * fueran estaticos: se crean roles nuevos y cada permiso puede tener una
 * vigencia, por ejemplo un docente que rota de ambiente (HU-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('es_sistema')
                ->default(false)
                ->after('name');
        });

        Schema::table('permission_role', function (Blueprint $table): void {
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();

            $table->unique(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::table('permission_role', function (Blueprint $table): void {
            $table->dropUnique(['role_id', 'permission_id']);
            $table->dropColumn(['vigente_desde', 'vigente_hasta']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('es_sistema');
        });
    }
};
