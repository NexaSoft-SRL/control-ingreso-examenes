<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Los campos:
        | is_active
        | failed_login_attempts
        | locked_until
        | last_login_at
        |
        | ya existen en la migración principal de usuarios.
        |--------------------------------------------------------------------------
        */

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE usuarios
                 ADD CONSTRAINT usuarios_failed_login_attempts_non_negative
                 CHECK (failed_login_attempts >= 0)'
            );
        }

        Schema::create('login_attempts', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('usuarios')
                ->nullOnDelete();

            $table->string('identifier');

            $table->boolean('successful')
                ->default(false);

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->timestamp('attempted_at')
                ->useCurrent();

            $table->index(
                ['identifier', 'attempted_at'],
                'login_attempts_identifier_attempted_at_idx'
            );

            $table->index(
                ['user_id', 'attempted_at'],
                'login_attempts_user_attempted_at_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE usuarios
                 DROP CONSTRAINT IF EXISTS usuarios_failed_login_attempts_non_negative'
            );
        }

        // No eliminamos columnas de usuarios porque pertenecen
        // a la migración principal.
    }
};
