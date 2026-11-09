<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El acceso deja de ser por correo: cada cuenta tiene un usuario unico y el
 * correo pasa a ser un dato de contacto opcional. La contrasena temporal
 * vence, y vencida la cuenta no entra hasta que se restablezca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('usuario', 60)
                ->nullable()
                ->after('name');

            $table->timestamp('password_temporal_expira_en')
                ->nullable()
                ->after('password_changed_at');
        });

        // Una base que ya tuviera cuentas no puede quedar con usuarios
        // nulos: se les pone uno provisional derivado del identificador.
        DB::table('users')
            ->whereNull('usuario')
            ->update(['usuario' => DB::raw("'usuario' || id")]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('usuario', 60)
                ->nullable(false)
                ->change();

            $table->unique('usuario', 'uq_users_usuario');

            $table->string('email')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('uq_users_usuario');
            $table->dropColumn(['usuario', 'password_temporal_expira_en']);
        });
    }
};
